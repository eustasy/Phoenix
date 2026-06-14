<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.torrent.edit.php';
require_once __DIR__.'/../../src/model/torrent.add.php';
require_once __DIR__.'/../../src/model/torrent.select.one.php';

class AdminTorrentEditActionTest extends PhoenixTestCase
{
    private const HASH = '__TEST_edit__';

    /** @var array<string, mixed> */
    private array $postBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postBackup = $_POST;
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->postBackup;
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
        parent::tearDown();
    }

    private function seed(): void
    {
        \torrent_add(self::$connection, self::$settings, [
            'user' => null,
            'info_hash' => self::HASH,
            'name' => 'Original',
            'size' => 100,
            'listed' => 1,
            'filename' => null,
            'files' => null,
            'trackers' => null,
            'webseeds' => null,
        ]);
    }

    public function testFullReplaceFromForm(): void
    {
        $this->seed();
        $_POST = [
            'name' => 'Edited',
            'size' => '777',
            'listed' => '1',
            'trackers' => "http://a/announce\nhttp://b/announce",
        ];

        $this->assertSame('Torrent updated.', \admin_torrent_edit_action(self::$connection, self::$settings, self::HASH));

        $row = \torrent_select_one(self::$connection, self::$settings, self::HASH);
        $this->assertIsArray($row);
        $this->assertSame('Edited', $row['name']);
        $this->assertSame(777, $row['size']);
        $this->assertSame(1, $row['listed']);
        $this->assertSame(['http://a/announce', 'http://b/announce'], $row['trackers']);
    }

    public function testUncheckedListedUnlists(): void
    {
        $this->seed();
        // The listed checkbox is absent (unchecked) → the torrent is delisted.
        $_POST = ['name' => 'Original', 'size' => '100'];

        \admin_torrent_edit_action(self::$connection, self::$settings, self::HASH);

        $row = \torrent_select_one(self::$connection, self::$settings, self::HASH);
        $this->assertIsArray($row);
        $this->assertSame(0, $row['listed']);
    }

    public function testReturnsNotFoundForUnknownHash(): void
    {
        $this->assertSame(
            'Torrent not found.',
            \admin_torrent_edit_action(self::$connection, self::$settings, '__TEST_missing__'),
        );
    }
}
