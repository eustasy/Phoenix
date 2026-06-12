<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentAddTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrent.add.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{user: string, info_hash: string, name: string|null, size: int, listed: int, filename: string|null, files: string|null, trackers: string|null, webseeds: string|null}
     */
    private function torrent(array $overrides = []): array
    {
        return array_merge([
            'user' => 'tester',
            'info_hash' => '__TEST_ADD_1__',
            'name' => 'Test Torrent',
            'size' => 1024,
            'listed' => 1,
            'filename' => null,
            'files' => null,
            'trackers' => null,
            'webseeds' => null,
        ], $overrides);
    }

    /** @return array<string, string|null>|false */
    private function fetchRow(string $info_hash): array|false
    {
        $result = mysqli_query(
            self::$connection,
            'SELECT `user`, `name`, `size`, `listed`, `downloads`, '.
            '`filename`, `files`, `trackers`, `webseeds` '.
            'FROM `'.self::$settings['db_prefix'].'torrents` '.
            'WHERE `info_hash` = \''.$info_hash.'\';',
        );

        return $result ? (mysqli_fetch_assoc($result) ?: false) : false;
    }

    public function testInsertsNewTorrent(): void
    {
        $this->assertTrue(torrent_add(self::$connection, self::$settings, $this->torrent()));

        $row = $this->fetchRow('__TEST_ADD_1__');
        $this->assertIsArray($row);
        $this->assertSame('tester', $row['user']);
        $this->assertSame('Test Torrent', $row['name']);
        $this->assertEquals(1024, $row['size']);
        $this->assertEquals(1, $row['listed']);
    }

    public function testDuplicateInfoHashReturnsExists(): void
    {
        torrent_add(self::$connection, self::$settings, $this->torrent());

        $this->assertSame('exists', torrent_add(self::$connection, self::$settings, $this->torrent()));
    }

    public function testDuplicateDoesNotModifyRow(): void
    {
        // Add-only is the ownership guard: a second add — even by another
        // user with new metadata — must leave the original row untouched.
        torrent_add(self::$connection, self::$settings, $this->torrent());

        $result = torrent_add(self::$connection, self::$settings, $this->torrent([
            'user' => 'other',
            'name' => 'Renamed',
            'size' => 2048,
            'listed' => 0,
        ]));

        $this->assertSame('exists', $result);

        $row = $this->fetchRow('__TEST_ADD_1__');
        $this->assertIsArray($row);
        $this->assertSame('tester', $row['user']);
        $this->assertSame('Test Torrent', $row['name']);
        $this->assertEquals(1024, $row['size']);
        $this->assertEquals(1, $row['listed']);
    }

    public function testAnnounceCreatedRowCannotBeAdded(): void
    {
        // event=completed announces auto-create torrent rows; those count as
        // existing too, so the API can't claim or overwrite them.
        require_once __DIR__.'/../../src/model/torrent.increment.downloads.php';
        torrent_increment_downloads(self::$connection, self::$settings, '__TEST_ADD_1__');

        $this->assertSame('exists', torrent_add(self::$connection, self::$settings, $this->torrent()));

        $row = $this->fetchRow('__TEST_ADD_1__');
        $this->assertIsArray($row);
        $this->assertNull($row['user']);
        $this->assertEquals(1, $row['downloads']);
    }

    public function testNullNameIsStored(): void
    {
        torrent_add(self::$connection, self::$settings, $this->torrent(['name' => null]));

        $row = $this->fetchRow('__TEST_ADD_1__');
        $this->assertIsArray($row);
        $this->assertNull($row['name']);
    }

    public function testMetaColumnsDefaultToNull(): void
    {
        torrent_add(self::$connection, self::$settings, $this->torrent());

        $row = $this->fetchRow('__TEST_ADD_1__');
        $this->assertIsArray($row);
        $this->assertNull($row['filename']);
        $this->assertNull($row['files']);
        $this->assertNull($row['trackers']);
        $this->assertNull($row['webseeds']);
    }

    public function testMetaColumnsStoredVerbatim(): void
    {
        // The model stores whatever storage strings the controller hands it.
        $files = '[{"path":"dir/a.mkv","length":100},{"path":"b.txt","length":5}]';
        $trackers = "http://primary/announce\nhttp://second/announce";
        $webseeds = 'http://seed.example/files/';

        torrent_add(self::$connection, self::$settings, $this->torrent([
            'filename' => 'movie.mkv',
            'files' => $files,
            'trackers' => $trackers,
            'webseeds' => $webseeds,
        ]));

        $row = $this->fetchRow('__TEST_ADD_1__');
        $this->assertIsArray($row);
        $this->assertSame('movie.mkv', $row['filename']);
        $this->assertSame($files, $row['files']);
        $this->assertSame($trackers, $row['trackers']);
        $this->assertSame($webseeds, $row['webseeds']);

        // The JSON column round-trips back to the structured list.
        $decoded = json_decode((string) $row['files'], true);
        $this->assertSame([
            ['path' => 'dir/a.mkv', 'length' => 100],
            ['path' => 'b.txt', 'length' => 5],
        ], $decoded);
    }
}
