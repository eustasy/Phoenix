<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentSelectOneTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrent.select.one.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    /**
     * @param array<string, string|int|null> $overrides
     */
    private function insertTorrent(array $overrides = []): void
    {
        $defaults = [
            'info_hash' => '__TEST_ONE_1__',
            'user' => 'tester',
            'name' => 'Test Torrent',
            'size' => 1024,
            'listed' => 1,
            'downloads' => 0,
            'filename' => null,
            'files' => null,
            'trackers' => null,
            'webseeds' => null,
        ];
        $row = array_merge($defaults, $overrides);

        $esc = static fn (mixed $v): string => $v === null
            ? 'NULL'
            : '\''.mysqli_real_escape_string(self::$connection, (string) $v).'\'';

        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `user`, `name`, `size`, `listed`, `downloads`, `filename`, `files`, `trackers`, `webseeds`) VALUES ('.
            $esc($row['info_hash']).', '.
            $esc($row['user']).', '.
            $esc($row['name']).', '.
            (int) $row['size'].', '.
            (int) $row['listed'].', '.
            (int) $row['downloads'].', '.
            $esc($row['filename']).', '.
            $esc($row['files']).', '.
            $esc($row['trackers']).', '.
            $esc($row['webseeds']).');',
        );
    }

    ////	absent

    public function testReturnsFalseWhenNotFound(): void
    {
        $result = \torrent_select_one(self::$connection, self::$settings, '__TEST_NONEXISTENT__');
        $this->assertFalse($result);
    }

    ////	shape

    public function testReturnsArrayOnFound(): void
    {
        $this->insertTorrent();
        $result = \torrent_select_one(self::$connection, self::$settings, '__TEST_ONE_1__');
        $this->assertIsArray($result);
    }

    public function testReturnShapeHasAllRequiredKeys(): void
    {
        $this->insertTorrent();
        $result = \torrent_select_one(self::$connection, self::$settings, '__TEST_ONE_1__');
        $this->assertIsArray($result);

        foreach (['info_hash', 'user', 'name', 'size', 'listed', 'downloads', 'filename', 'files', 'trackers', 'webseeds'] as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: $key");
        }
    }

    public function testScalarFieldTypes(): void
    {
        $this->insertTorrent(['size' => 2048, 'listed' => 1, 'downloads' => 7]);
        $result = \torrent_select_one(self::$connection, self::$settings, '__TEST_ONE_1__');
        $this->assertIsArray($result);

        $this->assertSame('__TEST_ONE_1__', $result['info_hash']);
        $this->assertIsString($result['info_hash']);
        $this->assertIsInt($result['size']);
        $this->assertSame(2048, $result['size']);
        $this->assertIsInt($result['listed']);
        $this->assertSame(1, $result['listed']);
        $this->assertIsInt($result['downloads']);
        $this->assertSame(7, $result['downloads']);
    }

    public function testNullableColumnsReturnNull(): void
    {
        // user and name are nullable; meta columns all NULL by default fixture.
        $this->insertTorrent(['user' => null, 'name' => null]);
        $result = \torrent_select_one(self::$connection, self::$settings, '__TEST_ONE_1__');
        $this->assertIsArray($result);

        $this->assertNull($result['user']);
        $this->assertNull($result['name']);
        $this->assertNull($result['filename']);
        $this->assertNull($result['files']);
        $this->assertNull($result['trackers']);
        $this->assertNull($result['webseeds']);
    }

    ////	meta normalization

    public function testMetaFilesDecodedFromJson(): void
    {
        $json = json_encode([['path' => 'movie.mkv', 'length' => 1073741824]]);
        $this->assertIsString($json);
        $this->insertTorrent(['files' => $json]);

        $result = \torrent_select_one(self::$connection, self::$settings, '__TEST_ONE_1__');
        $this->assertIsArray($result);
        $this->assertSame([['path' => 'movie.mkv', 'length' => 1073741824]], $result['files']);
    }

    public function testMetaTrackersDecodedFromNewlines(): void
    {
        $this->insertTorrent(['trackers' => "https://t1.example/\nhttps://t2.example/"]);

        $result = \torrent_select_one(self::$connection, self::$settings, '__TEST_ONE_1__');
        $this->assertIsArray($result);
        $this->assertSame(['https://t1.example/', 'https://t2.example/'], $result['trackers']);
    }

    public function testMetaWebseedsDecodedFromNewlines(): void
    {
        $this->insertTorrent(['webseeds' => "https://seed.example/\nhttps://seed2.example/"]);

        $result = \torrent_select_one(self::$connection, self::$settings, '__TEST_ONE_1__');
        $this->assertIsArray($result);
        $this->assertSame(['https://seed.example/', 'https://seed2.example/'], $result['webseeds']);
    }

    public function testMetaFilenamePassedThrough(): void
    {
        $this->insertTorrent(['filename' => 'my.movie.mkv']);

        $result = \torrent_select_one(self::$connection, self::$settings, '__TEST_ONE_1__');
        $this->assertIsArray($result);
        $this->assertSame('my.movie.mkv', $result['filename']);
    }
}
