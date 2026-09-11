<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/model/peer.insert.php'; // for insertPeer()

class TorrentsTopTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrents.top.php';
    }

    protected function tearDown(): void
    {
        $prefix = self::$settings['db_prefix'];
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'peers` WHERE `info_hash` LIKE \'__TEST_%\';');
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'torrents` WHERE `info_hash` LIKE \'__TEST_%\';');
        parent::tearDown();
    }

    private function torrent(string $hash, ?int $size = 1000, int $downloads = 0): void
    {
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES (?, ?, ?, 1, ?);',
            [$hash, $hash, $size, $downloads],
        );
    }

    /** Seeders are state 1, leechers state 0. */
    private function swarm(string $hash, int $seeders, int $leechers): void
    {
        for ($i = 0; $i < $seeders; $i++) {
            $this->insertPeer($hash, bin2hex(str_pad('s'.$i.$hash, 20, 'x')), 1, time());
        }
        for ($i = 0; $i < $leechers; $i++) {
            $this->insertPeer($hash, bin2hex(str_pad('l'.$i.$hash, 20, 'x')), 0, time());
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function hashes(array $rows): array
    {
        return array_values(array_filter(
            array_column($rows, 'info_hash'),
            static fn (string $h): bool => str_starts_with($h, '__TEST_'),
        ));
    }

    public function testSeedersMeasureRanksBySeederCount(): void
    {
        $this->torrent('__TEST_tt_big__');
        $this->torrent('__TEST_tt_small__');
        $this->swarm('__TEST_tt_big__', 3, 0);
        $this->swarm('__TEST_tt_small__', 1, 0);

        $rows = \torrents_top(self::$connection, self::$settings, 'seeders', 50);

        $this->assertSame(['__TEST_tt_big__', '__TEST_tt_small__'], $this->hashes($rows));
    }

    public function testSeedersMeasureExcludesTorrentsWithNone(): void
    {
        // The HAVING keeps a card from listing torrents with nothing to show.
        $this->torrent('__TEST_tt_idle__');
        $this->swarm('__TEST_tt_idle__', 0, 2);

        $this->assertSame([], $this->hashes(\torrents_top(self::$connection, self::$settings, 'seeders', 50)));
    }

    public function testLeechersMeasureRanksByLeecherCount(): void
    {
        $this->torrent('__TEST_tt_a__');
        $this->swarm('__TEST_tt_a__', 0, 2);

        $rows = \torrents_top(self::$connection, self::$settings, 'leechers', 50);

        $this->assertSame(['__TEST_tt_a__'], $this->hashes($rows));
        $this->assertSame(2, $rows[array_search('__TEST_tt_a__', array_column($rows, 'info_hash'), true)]['leechers']);
    }

    public function testTrafficMeasureNeedsASizeAndADownload(): void
    {
        // The row-level conditions are in WHERE, not HAVING — a plain column
        // cannot be filtered after grouping.
        $this->torrent('__TEST_tt_counted__', 1000, 5);
        $this->torrent('__TEST_tt_nosize__', null, 5);
        $this->torrent('__TEST_tt_nodl__', 1000, 0);

        $this->assertSame(
            ['__TEST_tt_counted__'],
            $this->hashes(\torrents_top(self::$connection, self::$settings, 'traffic', 50)),
        );
    }

    public function testTroubleMeasureFindsPoorlySeededSwarms(): void
    {
        // Leechers waiting on too few seeders: 1 of 9 is under the 0.25 share.
        $this->torrent('__TEST_tt_trouble__');
        $this->swarm('__TEST_tt_trouble__', 1, 8);
        // A healthy swarm must not appear.
        $this->torrent('__TEST_tt_healthy__');
        $this->swarm('__TEST_tt_healthy__', 8, 1);

        $this->assertSame(
            ['__TEST_tt_trouble__'],
            $this->hashes(\torrents_top(self::$connection, self::$settings, 'trouble', 50)),
        );
    }

    public function testUnknownMeasureFallsBackToSeeders(): void
    {
        $this->torrent('__TEST_tt_a__');
        $this->swarm('__TEST_tt_a__', 2, 0);

        $this->assertSame(
            $this->hashes(\torrents_top(self::$connection, self::$settings, 'seeders', 50)),
            $this->hashes(\torrents_top(self::$connection, self::$settings, 'nonsense', 50)),
        );
    }

    public function testLimitIsClamped(): void
    {
        $this->torrent('__TEST_tt_a__');
        $this->swarm('__TEST_tt_a__', 1, 0);

        // 0 clamps up to 1, so a caller cannot ask for an empty card.
        $this->assertLessThanOrEqual(1, count(\torrents_top(self::$connection, self::$settings, 'seeders', 0)));
    }
}
