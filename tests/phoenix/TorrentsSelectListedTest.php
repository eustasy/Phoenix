<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentsSelectListedTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrents.select.listed.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    private function insertTorrent(string $infoHash, string $name, int $size, int $listed, int $downloads): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.$infoHash.'\', \''.$name.'\', '.$size.', '.$listed.', '.$downloads.');',
        );
    }

    private function insertPeerRow(string $infoHash, string $peerId, int $state): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
            '(\''.$infoHash.'\', \''.$peerId.'\', \'\', \'\', 0, 0, '.$state.', '.self::$time.');',
        );
    }

    public function testReturnsEmptyArrayWhenNoTorrents(): void
    {
        $this->assertSame([], \torrents_select_listed(self::$connection, self::$settings));
    }

    public function testIgnoresUnlistedTorrents(): void
    {
        // listed=0 rows should not appear in the public index even when they
        // exist in the table (e.g. open-tracker torrents the operator hasn't
        // chosen to publish).
        $this->insertTorrent('__TEST_unlisted__', 'Hidden', 100, 0, 0);
        $this->assertSame([], \torrents_select_listed(self::$connection, self::$settings));
    }

    public function testReturnsListedTorrentsWithComputedFields(): void
    {
        // Single listed torrent with no peers — exercises the no-peer
        // IFNULL(SUM(...),0) branch and the `peers`/`traffic` derivations.
        $this->insertTorrent('__TEST_listed__', 'Solo', 1024, 1, 5);

        $result = \torrents_select_listed(self::$connection, self::$settings);

        $this->assertCount(1, $result);
        $this->assertSame([
            'info_hash' => '__TEST_listed__',
            'name' => 'Solo',
            'size' => 1024,
            'downloads' => 5,
            'seeders' => 0,
            'leechers' => 0,
            'peers' => 0,
            'traffic' => 1024 * 5,
        ], $result[0]);
    }

    public function testCountsSeedersAndLeechersFromPeers(): void
    {
        // state=1 → seeder, state=0 → leecher. peers = seeders + leechers,
        // so we can sanity-check the join arithmetic in one pass.
        $this->insertTorrent('__TEST_swarm__', 'Swarm', 2048, 1, 10);
        $this->insertPeerRow('__TEST_swarm__', '__TEST_peer_seed_1__', 1);
        $this->insertPeerRow('__TEST_swarm__', '__TEST_peer_seed_2__', 1);
        $this->insertPeerRow('__TEST_swarm__', '__TEST_peer_leech_1__', 0);

        $result = \torrents_select_listed(self::$connection, self::$settings);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['seeders']);
        $this->assertSame(1, $result[0]['leechers']);
        $this->assertSame(3, $result[0]['peers']);
        $this->assertSame(2048 * 10, $result[0]['traffic']);
    }

    public function testOrdersByName(): void
    {
        // SQL has ORDER BY t.name; insert out of order so an unsorted
        // fetch would visibly fail this assertion.
        $this->insertTorrent('__TEST_charlie__', 'Charlie', 0, 1, 0);
        $this->insertTorrent('__TEST_alpha__', 'Alpha', 0, 1, 0);
        $this->insertTorrent('__TEST_bravo__', 'Bravo', 0, 1, 0);

        $names = array_column(
            \torrents_select_listed(self::$connection, self::$settings),
            'name',
        );
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $names);
    }

}
