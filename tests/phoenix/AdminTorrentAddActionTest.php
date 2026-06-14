<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.torrent.add.php';

// The admin add-torrent action returns its message on every outcome (it never
// calls tracker_error/exit), so every case runs in-process — no subprocess is
// needed. Admin-added torrents record a NULL owner, which the assertions verify.
class AdminTorrentAddActionTest extends PhoenixTestCase
{
    // 40-char hex info_hash; the action runs it through maybe_binary_to_hex,
    // which accepts 40-char hex unchanged.
    private const HASH = 'cccccccccccccccccccccccccccccccccccccccc';

    private int $errorReporting;

    /** @var array<string, mixed> */
    private array $getBackup;

    /** @var array<string, mixed> */
    private array $postBackup;

    /** @var array<string, mixed> */
    private array $filesBackup;

    /** @var list<string> temp files created to fake uploads, removed in tearDown */
    private array $tmpUploads = [];

    protected function setUp(): void
    {
        parent::setUp();
        // The action sets no headers, so no "headers already sent" suppression
        // is strictly needed; we still preserve the superglobals it reads.
        $this->errorReporting = error_reporting();
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->filesBackup = $_FILES;
        $_GET = [];
        $_POST = [];
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_FILES = $this->filesBackup;
        foreach ($this->tmpUploads as $path) {
            @unlink($path);
        }
        $this->tmpUploads = [];
        // The explicit-field hash plus every parsed-fixture hash land in the
        // table across these tests; clean them all up.
        foreach ([
            self::HASH,
            $this->torrentInfoHash('Admin Upload.iso', 9000),
            $this->torrentInfoHash('Dropped.iso', 7777),
            $this->torrentInfoHash('FromFile.iso', 5000),
        ] as $hash) {
            mysqli_query(
                self::$connection,
                'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.$hash.'\';',
            );
        }
        parent::tearDown();
    }

    /** @return array<string, string|null>|false */
    private function fetchRow(string $info_hash): array|false
    {
        $result = mysqli_query(
            self::$connection,
            'SELECT `user`, `name`, `size`, `listed` '.
            'FROM `'.self::$settings['db_prefix'].'torrents` '.
            'WHERE `info_hash` = \''.$info_hash.'\';',
        );

        return $result ? (mysqli_fetch_assoc($result) ?: false) : false;
    }

    /**
     * Build a bencoded single-file .torrent. The info_hash the parser derives
     * is sha1() of the encoded info dict.
     */
    private function buildTorrent(string $name, int $length): string
    {
        require_once __DIR__.'/../../src/functions/bencode.encode.php';

        return \bencode_encode([
            'info' => [
                'name' => $name,
                'length' => $length,
                'piece length' => 16384,
                'pieces' => str_repeat("\x00", 20),
            ],
        ]);
    }

    private function torrentInfoHash(string $name, int $length): string
    {
        require_once __DIR__.'/../../src/functions/bencode.encode.php';

        return sha1(\bencode_encode([
            'name' => $name,
            'length' => $length,
            'piece length' => 16384,
            'pieces' => str_repeat("\x00", 20),
        ]));
    }

    /**
     * Fake a multipart upload by writing $raw to a temp file and populating
     * $_FILES['torrent'] as the SAPI would. Cleaned up in tearDown.
     */
    private function fakeUpload(string $raw, int $error = UPLOAD_ERR_OK): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phx_upload_');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $raw);
        $this->tmpUploads[] = $tmp;

        $_FILES['torrent'] = [
            'name' => 'fixture.torrent',
            'type' => 'application/x-bittorrent',
            'tmp_name' => $tmp,
            'error' => $error,
            'size' => strlen($raw),
        ];
    }

    public function testAddsTorrentWithExplicitFieldsAsNullOwner(): void
    {
        $_POST = [
            'info_hash' => self::HASH,
            'name' => 'Admin Torrent',
            'size' => '4096',
            'listed' => '1',
        ];

        $message = \admin_torrent_add_action(self::$connection, self::$settings);
        $this->assertSame('Torrent added.', $message);

        // The row landed with a NULL owner — admin-added torrents have none.
        $row = $this->fetchRow(self::HASH);
        $this->assertIsArray($row);
        $this->assertNull($row['user']);
        $this->assertSame('Admin Torrent', $row['name']);
        $this->assertEquals(4096, $row['size']);
        $this->assertEquals(1, $row['listed']);
    }

    public function testRejectsDuplicateTorrent(): void
    {
        $_POST = ['info_hash' => self::HASH, 'name' => 'First'];
        $this->assertSame('Torrent added.', \admin_torrent_add_action(self::$connection, self::$settings));

        // Add-only: a second add of the same hash is refused, not updated.
        $_POST = ['info_hash' => self::HASH, 'name' => 'Second'];
        $this->assertSame('Torrent already exists.', \admin_torrent_add_action(self::$connection, self::$settings));

        $row = $this->fetchRow(self::HASH);
        $this->assertIsArray($row);
        $this->assertSame('First', $row['name']);
    }

    public function testUploadSuppliesBaseFields(): void
    {
        // No explicit info_hash/name/size: all come from the parsed upload.
        $hash = $this->torrentInfoHash('Admin Upload.iso', 9000);
        $this->fakeUpload($this->buildTorrent('Admin Upload.iso', 9000));
        $_POST = [];

        $message = \admin_torrent_add_action(self::$connection, self::$settings);
        $this->assertSame('Torrent added.', $message);

        $row = $this->fetchRow($hash);
        $this->assertIsArray($row);
        $this->assertNull($row['user']);
        $this->assertSame('Admin Upload.iso', $row['name']);
        $this->assertEquals(9000, $row['size']);
    }

    public function testFormBlankFieldsFallBackToUpload(): void
    {
        // The drag-and-drop form posts every field, blank ones as '' — those
        // must not clobber the parsed upload. Regression: '' was treated as a
        // real value, so the empty info_hash field failed validation even though
        // the file carried a valid one.
        $hash = $this->torrentInfoHash('Dropped.iso', 7777);
        $this->fakeUpload($this->buildTorrent('Dropped.iso', 7777));
        $_POST = [
            'info_hash' => '',
            'name' => '',
            'size' => '',
            'filename' => '',
            'files' => '',
            'trackers' => '',
            'webseeds' => '',
            'listed' => '1',
        ];

        $message = \admin_torrent_add_action(self::$connection, self::$settings);
        $this->assertSame('Torrent added.', $message);

        $row = $this->fetchRow($hash);
        $this->assertIsArray($row);
        $this->assertSame('Dropped.iso', $row['name']);
        $this->assertEquals(7777, $row['size']);
    }

    public function testFilledFieldsOverrideUploadButBlanksFallBack(): void
    {
        // A typed-in field takes priority over the file; left-blank fields still
        // fall back to the parsed value.
        $hash = $this->torrentInfoHash('FromFile.iso', 5000);
        $this->fakeUpload($this->buildTorrent('FromFile.iso', 5000));
        $_POST = [
            'info_hash' => '',               // blank → from file
            'name' => 'Overridden Name',     // typed → wins
            'size' => '',                    // blank → from file
            'listed' => '1',
        ];

        $message = \admin_torrent_add_action(self::$connection, self::$settings);
        $this->assertSame('Torrent added.', $message);

        $row = $this->fetchRow($hash);
        $this->assertIsArray($row);
        $this->assertSame('Overridden Name', $row['name']);
        $this->assertEquals(5000, $row['size']);
    }

    public function testRejectsMalformedUpload(): void
    {
        // A non-bencode payload fails torrent_parse() -> invalid.
        $this->fakeUpload('this is not bencode');
        $_POST = [];

        $this->assertSame(
            'Torrent file is invalid.',
            \admin_torrent_add_action(self::$connection, self::$settings),
        );
    }

    public function testRejectsOversizeUpload(): void
    {
        // A .torrent larger than torrent_upload_max is refused before parsing.
        $this->fakeUpload($this->buildTorrent('Big.iso', 1));
        $_POST = [];

        $settings = self::$settings;
        $settings['torrent_upload_max'] = 10;

        $this->assertSame(
            'Torrent file is too large.',
            \admin_torrent_add_action(self::$connection, $settings),
        );
    }
}
