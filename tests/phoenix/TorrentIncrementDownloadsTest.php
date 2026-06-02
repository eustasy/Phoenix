<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentIncrementDownloadsTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrent.increment.downloads.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    public function testInsertsRowWithDownloadCountOne(): void
    {
        $info_hash = '__TEST_1__';
        $this->assertTrue(torrent_increment_downloads(self::$connection, self::$settings, $info_hash));

        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `downloads` FROM `'.self::$settings['db_prefix'].'torrents` '.
            'WHERE `info_hash` = \'__TEST_1__\';',
        ));
        $this->assertIsArray($row);
        $this->assertEquals(1, $row['downloads']);
    }

    public function testIncrementsExistingRow(): void
    {
        $info_hash = '__TEST_1__';
        torrent_increment_downloads(self::$connection, self::$settings, $info_hash);
        torrent_increment_downloads(self::$connection, self::$settings, $info_hash);

        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `downloads` FROM `'.self::$settings['db_prefix'].'torrents` '.
            'WHERE `info_hash` = \'__TEST_1__\';',
        ));
        $this->assertIsArray($row);
        $this->assertEquals(2, $row['downloads']);
    }

}
