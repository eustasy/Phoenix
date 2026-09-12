<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentsBandwidthTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrents.bandwidth.php';
    }

    protected function tearDown(): void
    {
        $prefix = self::$settings['db_prefix'];
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'peers` WHERE `info_hash` LIKE \'__TEST_%\';');
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'torrents` WHERE `info_hash` LIKE \'__TEST_%\';');
    }

    private function torrent(string $hash, int $size, int $downloads, string $name = 'N', ?string $user = null): void
    {
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `user`, `size`, `listed`, `downloads`) VALUES (?, ?, ?, ?, 1, ?);',
            [$hash, $name, $user, $size, $downloads],
        );
    }

    private function peer(string $hash, string $peerId, int $uploaded, int $downloaded): void
    {
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            // compactv4/compactv6/portv6 are NOT NULL with no default.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `ipv4`, `portv4`, `portv6`, '.
            '`uploaded`, `downloaded`, `left`, `state`, `updated`) '.
            'VALUES (?, ?, \'\', \'\', \'1.2.3.4\', 51413, 0, ?, ?, 0, 1, ?);',
            [$hash, $peerId, $uploaded, $downloaded, time()],
        );
    }

    /** @param list<array<string, mixed>> $rows */
    private function find(array $rows, string $hash): ?array
    {
        foreach ($rows as $row) {
            if ($row['info_hash'] === $hash) {
                return $row;
            }
        }

        return null;
    }

    public function testEstimatedBandwidthIsSizeTimesDownloads(): void
    {
        $this->torrent('__TEST_tt_a__', 1000, 7);

        $row = $this->find(\torrents_bandwidth(self::$connection, self::$settings, 'events', 500, 0, '__TEST_tt_'), '__TEST_tt_a__');

        $this->assertNotNull($row);
        $this->assertSame(7000, $row['estimated']);
        $this->assertSame(1000, $row['size']);
        $this->assertSame(7, $row['downloads']);
    }

    public function testPeersMeasureSumsTheLiveCounters(): void
    {
        $this->torrent('__TEST_tt_a__', 1000, 0);
        $this->peer('__TEST_tt_a__', '__TEST_p1__', 500, 100);
        $this->peer('__TEST_tt_a__', '__TEST_p2__', 250, 50);

        $row = $this->find(\torrents_bandwidth(self::$connection, self::$settings, 'peers', 500, 0, '__TEST_tt_'), '__TEST_tt_a__');

        $this->assertNotNull($row);
        $this->assertSame(750, $row['uploaded']);
        $this->assertSame(150, $row['downloaded']);
        $this->assertSame(2, $row['peers']);
    }

    public function testCarriesBothFiguresWhicheverMeasureOrdered(): void
    {
        // The view shows the other as context, so neither may be dropped.
        $this->torrent('__TEST_tt_a__', 1000, 3);
        $this->peer('__TEST_tt_a__', '__TEST_p1__', 500, 100);

        foreach (['events', 'peers'] as $measure) {
            $row = $this->find(\torrents_bandwidth(self::$connection, self::$settings, $measure, 500, 0, '__TEST_tt_'), '__TEST_tt_a__');
            $this->assertSame(3000, $row['estimated'], $measure);
            $this->assertSame(500, $row['uploaded'], $measure);
        }
    }

    public function testTorrentWithNoPeersStillAppearsWithZeroes(): void
    {
        // A LEFT JOIN, so an idle torrent is not dropped from the listing.
        $this->torrent('__TEST_tt_idle__', 1000, 2);

        $row = $this->find(\torrents_bandwidth(self::$connection, self::$settings, 'events', 500, 0, '__TEST_tt_'), '__TEST_tt_idle__');

        $this->assertNotNull($row);
        $this->assertSame(0, $row['uploaded']);
        $this->assertSame(0, $row['peers']);
    }

    public function testSearchMatchesTheNameAndNarrowsTheResult(): void
    {
        $this->torrent('__TEST_tt_a__', 1000, 1, '__TEST_Findable__');
        $this->torrent('__TEST_tt_b__', 1000, 1, '__TEST_Other__');

        $rows = \torrents_bandwidth(self::$connection, self::$settings, 'events', 500, 0, '__TEST_Findable__');

        $this->assertNotNull($this->find($rows, '__TEST_tt_a__'));
        $this->assertNull($this->find($rows, '__TEST_tt_b__'));
    }

    public function testInfoHashNarrowsToOneTorrent(): void
    {
        $this->torrent('__TEST_tt_a__', 1000, 1);
        $this->torrent('__TEST_tt_b__', 1000, 1);

        $rows = \torrents_bandwidth(self::$connection, self::$settings, 'events', 500, 0, '', '__TEST_tt_a__');

        $this->assertCount(1, $rows);
        $this->assertSame('__TEST_tt_a__', $rows[0]['info_hash']);
    }

    public function testLimitAndOffsetPageTheListing(): void
    {
        $this->torrent('__TEST_tt_a__', 1000, 9);
        $this->torrent('__TEST_tt_b__', 1000, 8);

        $first = \torrents_bandwidth(self::$connection, self::$settings, 'events', 1, 0, '__TEST_tt_');
        $second = \torrents_bandwidth(self::$connection, self::$settings, 'events', 1, 1, '__TEST_tt_');

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertNotSame($first[0]['info_hash'], $second[0]['info_hash']);
    }

    public function testSortIsWhitelistedAndFallsBackToTheMeasure(): void
    {
        $this->torrent('__TEST_tt_a__', 1000, 1, '__TEST_Aaa__');
        $this->torrent('__TEST_tt_b__', 5000, 1, '__TEST_Bbb__');

        // An unknown key must not reach the query; it falls back to 'bandwidth',
        // which for the events measure is size x downloads — so the 5000 leads.
        $rows = \torrents_bandwidth(self::$connection, self::$settings, 'events', 500, 0, '__TEST_tt_', '', 'nonsense; DROP TABLE torrents');

        $this->assertSame('__TEST_tt_b__', $rows[0]['info_hash']);
    }

    public function testSortBySizeAscendingHonoursTheDirection(): void
    {
        $this->torrent('__TEST_tt_a__', 1000, 1);
        $this->torrent('__TEST_tt_b__', 5000, 1);

        $rows = \torrents_bandwidth(self::$connection, self::$settings, 'events', 500, 0, '__TEST_tt_', '', 'size', 'asc');

        $this->assertSame('__TEST_tt_a__', $rows[0]['info_hash']);
    }
}
