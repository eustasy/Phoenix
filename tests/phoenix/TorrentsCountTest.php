<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentsCountTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrents.count.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    private function insertTorrent(string $infoHash): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.$infoHash.'\', \'Name\', 0, 1, 0);',
        );
    }

    public function testCountReflectsInsertedRows(): void
    {
        // Counts every torrent (any owner/listed state), so assert the delta
        // around our seeded rows rather than an absolute total (the shared test
        // DB may hold others).
        $before = \torrents_count(self::$connection, self::$settings);

        $this->insertTorrent('__TEST_count_1__');
        $this->insertTorrent('__TEST_count_2__');

        $this->assertSame($before + 2, \torrents_count(self::$connection, self::$settings));
    }
}
