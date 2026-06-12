<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/api.torrent.add.php';

class ApiTorrentAddControllerTest extends PhoenixTestCase
{
    // 40-char hex info_hash (the controller runs it through
    // maybe_binary_to_hex, which accepts 40-char hex unchanged).
    private const HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const API_KEY = '__TEST_api_key__';

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
        // Suppress the harmless "headers already sent" warning the
        // controller's header() calls would emit under PHPUnit, and
        // preserve $_GET/$_POST/$_FILES across tests.
        $this->errorReporting = error_reporting();
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->filesBackup = $_FILES;
        error_reporting(0);
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
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.self::HASH.'\';',
        );
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function settingsWithKeys(): array
    {
        $settings = self::$settings;
        $settings['api_keys'] = ['tester' => self::API_KEY];

        return $settings;
    }

    /** @return array<string, string|null>|false */
    private function fetchMetaRow(string $info_hash): array|false
    {
        $result = mysqli_query(
            self::$connection,
            'SELECT `filename`, `files`, `trackers`, `webseeds`, `name`, `size` '.
            'FROM `'.self::$settings['db_prefix'].'torrents` '.
            'WHERE `info_hash` = \''.$info_hash.'\';',
        );

        return $result ? (mysqli_fetch_assoc($result) ?: false) : false;
    }

    /**
     * Build a bencoded single-file .torrent for $info_hash-independent fixtures.
     * Returns the raw bytes; the info_hash is derived by the parser from these.
     *
     * @param array<string, mixed> $extra extra top-level keys (announce, url-list)
     */
    private function buildTorrent(string $name, int $length, array $extra = []): string
    {
        require_once __DIR__.'/../../src/functions/bencode.encode.php';

        $info = [
            'name' => $name,
            'length' => $length,
            'piece length' => 16384,
            'pieces' => str_repeat("\x00", 20),
        ];

        return \bencode_encode(['info' => $info] + $extra);
    }

    /**
     * Compute the info_hash a parser would derive for a single-file fixture, so
     * tests can assert/clean up the row a .torrent upload creates.
     *
     * @param array<string, mixed> $extra
     */
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

    public function testPersistsMetaParameters(): void
    {
        $_GET = [];
        $_POST = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'filename' => 'movie.mkv',
            'files' => '[{"path":"a/b.mkv","length":42},{"path":"c.txt","length":7}]',
            'trackers' => "http://primary/announce\nhttp://second/announce",
            'webseeds' => 'http://seed.example/files/',
        ];

        $json = \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        // The response carries normalized meta.
        $this->assertSame('movie.mkv', $decoded['torrent']['filename']);
        $this->assertSame([
            ['path' => 'a/b.mkv', 'length' => 42],
            ['path' => 'c.txt', 'length' => 7],
        ], $decoded['torrent']['files']);
        $this->assertSame(['http://primary/announce', 'http://second/announce'], $decoded['torrent']['trackers']);
        $this->assertSame(['http://seed.example/files/'], $decoded['torrent']['webseeds']);

        // The DB holds the exact storage encodings.
        $row = $this->fetchMetaRow(self::HASH);
        $this->assertIsArray($row);
        $this->assertSame('movie.mkv', $row['filename']);
        $this->assertSame('[{"path":"a\/b.mkv","length":42},{"path":"c.txt","length":7}]', $row['files']);
        $this->assertSame("http://primary/announce\nhttp://second/announce", $row['trackers']);
        $this->assertSame('http://seed.example/files/', $row['webseeds']);
    }

    public function testInvalidFilesJsonStoredAsNull(): void
    {
        $_GET = [];
        $_POST = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'files' => 'not-json-at-all',
        ];

        $json = \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertNull($decoded['torrent']['files']);

        $row = $this->fetchMetaRow(self::HASH);
        $this->assertIsArray($row);
        $this->assertNull($row['files']);
    }

    public function testFilesJsonWithNoValidEntriesStoredAsNull(): void
    {
        // Valid JSON list, but every element is malformed -> null.
        $_GET = [];
        $_POST = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'files' => '[{"path":"x"},{"length":5},{"path":"y","length":-1}]',
        ];

        \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());

        $row = $this->fetchMetaRow(self::HASH);
        $this->assertIsArray($row);
        $this->assertNull($row['files']);
    }

    public function testTrackersUrlFilteringAndDedup(): void
    {
        // Blank lines, a non-URL token, and a duplicate are all dropped; order
        // is preserved.
        $_GET = [];
        $_POST = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'trackers' => "http://a/announce\n\nnot a url\n  http://b/announce  \nhttp://a/announce",
        ];

        $json = \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(['http://a/announce', 'http://b/announce'], $decoded['torrent']['trackers']);

        $row = $this->fetchMetaRow(self::HASH);
        $this->assertIsArray($row);
        $this->assertSame("http://a/announce\nhttp://b/announce", $row['trackers']);
    }

    public function testTrackersAllInvalidStoredAsNull(): void
    {
        $_GET = [];
        $_POST = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'webseeds' => "not a url\nalso-bad",
        ];

        \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());

        $row = $this->fetchMetaRow(self::HASH);
        $this->assertIsArray($row);
        $this->assertNull($row['webseeds']);
    }

    public function testTorrentUploadSuppliesBaseFields(): void
    {
        // No explicit info_hash/name/size: all come from the parsed upload.
        $hash = $this->torrentInfoHash('Uploaded.iso', 9000);
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.$hash.'\';',
        );

        $this->fakeUpload($this->buildTorrent('Uploaded.iso', 9000, [
            'announce' => 'http://tracker.example/announce',
            'url-list' => 'http://seed.example/files/',
        ]));
        $_GET = [];
        $_POST = ['key' => self::API_KEY];

        $json = \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame($hash, $decoded['torrent']['info_hash']);
        $this->assertSame('Uploaded.iso', $decoded['torrent']['name']);
        $this->assertSame(9000, $decoded['torrent']['size']);
        $this->assertSame('Uploaded.iso', $decoded['torrent']['filename']);
        $this->assertSame([['path' => 'Uploaded.iso', 'length' => 9000]], $decoded['torrent']['files']);
        $this->assertSame(['http://tracker.example/announce'], $decoded['torrent']['trackers']);
        $this->assertSame(['http://seed.example/files/'], $decoded['torrent']['webseeds']);

        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.$hash.'\';',
        );
    }

    public function testExplicitParamsOverrideUpload(): void
    {
        // An explicit info_hash, name, and trackers override the parsed values;
        // unspecified fields (size, filename, files, webseeds) keep the parsed base.
        $this->fakeUpload($this->buildTorrent('Parsed Name', 1234, [
            'announce' => 'http://parsed/announce',
            'url-list' => 'http://parsed-seed/',
        ]));
        $_GET = [];
        $_POST = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'name' => 'Override Name',
            'trackers' => 'http://override/announce',
        ];

        $json = \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        // Overridden:
        $this->assertSame(self::HASH, $decoded['torrent']['info_hash']);
        $this->assertSame('Override Name', $decoded['torrent']['name']);
        $this->assertSame(['http://override/announce'], $decoded['torrent']['trackers']);
        // Parsed base survives where not overridden:
        $this->assertSame(1234, $decoded['torrent']['size']);
        $this->assertSame('Parsed Name', $decoded['torrent']['filename']);
        $this->assertSame(['http://parsed-seed/'], $decoded['torrent']['webseeds']);
    }

    public function testRejectsOversizeUpload(): void
    {
        // A .torrent larger than torrent_upload_max is refused before parsing.
        $raw = $this->buildTorrent('Big.iso', 1);
        $result = $this->runErrorSubprocess(
            ['key' => self::API_KEY],
            upload: $raw,
            upload_max: 10,
        );

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Torrent file is too large.', $result['stdout']);
    }

    public function testRejectsMalformedUpload(): void
    {
        // A non-bencode payload fails torrent_parse() -> invalid.
        $result = $this->runErrorSubprocess(
            ['key' => self::API_KEY],
            upload: 'this is not bencode',
        );

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Torrent file is invalid.', $result['stdout']);
    }

    public function testAddsTorrentAndRendersJsonByDefault(): void
    {
        $_GET = [];
        $_POST = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'name' => 'API Torrent',
            'size' => '4096',
        ];

        $json = \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('tester', $decoded['torrent']['user']);
        $this->assertSame(self::HASH, $decoded['torrent']['info_hash']);
        $this->assertSame('API Torrent', $decoded['torrent']['name']);
        $this->assertSame(4096, $decoded['torrent']['size']);
        $this->assertSame(1, $decoded['torrent']['listed']);

        // The row landed with the key's user attached.
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `user`, `listed` FROM `'.self::$settings['db_prefix'].'torrents` '.
            'WHERE `info_hash` = \''.self::HASH.'\';',
        ));
        $this->assertIsArray($row);
        $this->assertSame('tester', $row['user']);
        $this->assertEquals(1, $row['listed']);
    }

    public function testRendersXmlWhenXmlFlagSet(): void
    {
        $_GET = ['xml' => '1'];
        $_POST = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
        ];

        $xml = \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());

        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertStringContainsString('<torrent>', $xml);
        $this->assertStringContainsString('<user>tester</user>', $xml);
        $this->assertStringContainsString('<info_hash>'.self::HASH.'</info_hash>', $xml);
    }

    public function testAcceptsParametersFromGet(): void
    {
        $_POST = [];
        $_GET = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'listed' => '0',
        ];

        $json = \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(0, $decoded['torrent']['listed']);
    }

    public function testRejectsDuplicateTorrent(): void
    {
        // Add once in-process, then attempt the same hash — even with a
        // different valid key — in a subprocess: add-only means the second
        // attempt errors rather than updating, and the row keeps its data.
        $_GET = [];
        $_POST = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'name' => 'First',
        ];
        \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());

        $result = $this->runErrorSubprocess([
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'name' => 'Second',
        ]);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Torrent already exists.', $result['stdout']);

        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `name` FROM `'.self::$settings['db_prefix'].'torrents` '.
            'WHERE `info_hash` = \''.self::HASH.'\';',
        ));
        $this->assertIsArray($row);
        $this->assertSame('First', $row['name']);
    }

    public function testRejectsMissingInfoHash(): void
    {
        $result = $this->runErrorSubprocess(['key' => self::API_KEY]);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Info Hash is invalid.', $result['stdout']);
    }

    /**
     * Run the controller in a subprocess with $_GET primed (tracker_error
     * exits, which would otherwise kill the PHPUnit worker). When $upload is
     * given, the subprocess writes it to a temp file and fakes
     * $_FILES['torrent'] so the upload path runs; $upload_max overrides the
     * size cap so the oversize branch can be exercised cheaply.
     *
     * @param array<string, string> $params
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runErrorSubprocess(
        array $params,
        ?string $upload = null,
        ?int $upload_max = null,
    ): array {
        $params['json'] = '1';
        $api_keys = ['tester' => self::API_KEY];

        $files_setup = '';
        if ($upload !== null) {
            $files_setup =
                '$tmp = tempnam(sys_get_temp_dir(), \'phx_upload_\');'.
                'file_put_contents($tmp, '.var_export($upload, true).');'.
                '$_FILES[\'torrent\'] = ['.
                '\'name\' => \'fixture.torrent\','.
                '\'type\' => \'application/x-bittorrent\','.
                '\'tmp_name\' => $tmp,'.
                '\'error\' => UPLOAD_ERR_OK,'.
                '\'size\' => strlen('.var_export($upload, true).'),'.
                '];';
        }

        $max_override = $upload_max === null
            ? ''
            : '$settings[\'torrent_upload_max\'] = '.$upload_max.';';

        return $this->runPhpSubprocess(
            '<?php
            $_GET = '.var_export($params, true).';
            '.$files_setup.'
            require_once '.var_export(dirname(__DIR__).'/bootstrap.php', true).';
            require_once '.var_export(dirname(__DIR__, 2).'/src/controller/api.torrent.add.php', true).';
            $settings = $GLOBALS[\'phoenix_settings\'];
            $settings[\'api_keys\'] = '.var_export($api_keys, true).';
            '.$max_override.'
            echo api_torrent_add_controller($GLOBALS[\'phoenix_connection\'], $settings);
            ',
        );
    }
}
