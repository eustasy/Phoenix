<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/model/stats.traffic.series.php';

class StatsTrafficSeriesTest extends PhoenixTestCase
{
    // A sentinel torrent, so the assertions hold regardless of other rows in
    // the ledger: only these events reference this hash.
    private const HASH = 'dddddddddddddddddddddddddddddddddddddddd';

    protected function tearDown(): void
    {
        $prefix = self::$settings['db_prefix'];
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'events` WHERE `info_hash` = \''.self::HASH.'\';');
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'torrents` WHERE `info_hash` = \''.self::HASH.'\';');
        parent::tearDown();
    }

    private function torrent(int $size): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` (`info_hash`, `name`, `size`, `listed`, `downloads`) '.
            'VALUES (\''.self::HASH.'\', \'__TEST_TrafficSeries__\', '.$size.', 1, 0);',
        );
    }

    private function completedAt(int $time): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'events` (`time`, `info_hash`, `event`, `country`) '.
            'VALUES ('.$time.', \''.self::HASH.'\', \'completed\', \'ZZ\');',
        );
    }

    /** @param list<array{time: int, completions: int, bytes: int}> $series */
    private function bucketAt(array $series, int $start): ?array
    {
        foreach ($series as $point) {
            if ($point['time'] === $start) {
                return $point;
            }
        }

        return null;
    }

    public function testDropsTheBucketStillInProgress(): void
    {
        // A part-elapsed day always reads as a fall, and the most recent point
        // is the one read hardest — so it is omitted rather than shown as a
        // collapse in traffic.
        $today = intdiv(time(), 86400) * 86400;
        $yesterday = $today - 86400;

        $this->torrent(1000);
        $this->completedAt($yesterday + 3600);
        $this->completedAt(time());

        $series = \stats_traffic_series(self::$connection, self::$settings, 7, 86400);

        $this->assertNotNull($this->bucketAt($series, $yesterday), 'a complete day is kept');
        $this->assertNull($this->bucketAt($series, $today), 'the day in progress is dropped');
    }

    public function testWeightsCompletionsByTorrentSize(): void
    {
        $yesterday = (intdiv(time(), 86400) * 86400) - 86400;

        $this->torrent(1024);
        $this->completedAt($yesterday + 60);
        $this->completedAt($yesterday + 120);

        $point = $this->bucketAt(\stats_traffic_series(self::$connection, self::$settings, 7, 86400), $yesterday);

        $this->assertNotNull($point);
        $this->assertSame(2, $point['completions']);
        $this->assertSame(2048, $point['bytes']);
    }
}
