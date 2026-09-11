<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class EventsGeoTrafficTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/events.geo.traffic.php';
    }

    protected function tearDown(): void
    {
        $prefix = self::$settings['db_prefix'];
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'events` WHERE `info_hash` LIKE \'__TEST_%\';');
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'torrents` WHERE `info_hash` LIKE \'__TEST_%\';');
    }

    private function torrent(string $hash, ?int $size): void
    {
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES (?, \'N\', ?, 1, 0);',
            [$hash, $size],
        );
    }

    private function completion(string $hash, string $country, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            mysqli_execute_query(
                self::$connection,
                'INSERT INTO `'.self::$settings['db_prefix'].'events` '.
                '(`time`, `info_hash`, `event`, `country`) VALUES (?, ?, \'completed\', ?);',
                [time(), $hash, $country],
            );
        }
    }

    public function testWeightsEachCompletionByItsTorrentSize(): void
    {
        $this->torrent('__TEST_egt_a__', 1000);
        $this->completion('__TEST_egt_a__', 'GB', 3);

        $traffic = \events_geo_traffic(self::$connection, self::$settings);

        $this->assertSame(3000, $traffic['GB'] ?? 0);
    }

    public function testSumsSeveralTorrentsIntoOneCountry(): void
    {
        $this->torrent('__TEST_egt_a__', 1000);
        $this->torrent('__TEST_egt_b__', 500);
        $this->completion('__TEST_egt_a__', 'GB', 2);
        $this->completion('__TEST_egt_b__', 'GB', 1);

        $this->assertSame(2500, \events_geo_traffic(self::$connection, self::$settings)['GB'] ?? 0);
    }

    public function testCountryCodeIsUppercased(): void
    {
        $this->torrent('__TEST_egt_a__', 1000);
        $this->completion('__TEST_egt_a__', 'gb');

        $traffic = \events_geo_traffic(self::$connection, self::$settings);

        $this->assertArrayHasKey('GB', $traffic);
        $this->assertArrayNotHasKey('gb', $traffic);
    }

    public function testCompletionWithNoRecordedSizeContributesNothing(): void
    {
        // Matches the join's IFNULL(size, 0): counted, but worth no bytes.
        $this->torrent('__TEST_egt_null__', null);
        $this->completion('__TEST_egt_null__', 'FR', 5);

        $this->assertArrayNotHasKey('FR', \events_geo_traffic(self::$connection, self::$settings));
    }

    public function testCompletionForARemovedTorrentContributesNothing(): void
    {
        // No torrents row at all — the event outlived its torrent.
        $this->completion('__TEST_egt_gone__', 'DE', 4);

        $this->assertArrayNotHasKey('DE', \events_geo_traffic(self::$connection, self::$settings));
    }

    public function testIgnoresUntaggedAndNonCompletionEvents(): void
    {
        $this->torrent('__TEST_egt_a__', 1000);
        // No country on the row.
        $this->completion('__TEST_egt_a__', '', 3);
        // A completion's sibling event type.
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'events` '.
            '(`time`, `info_hash`, `event`, `country`) VALUES (?, ?, \'started\', \'ES\');',
            [time(), '__TEST_egt_a__'],
        );

        $traffic = \events_geo_traffic(self::$connection, self::$settings);

        $this->assertArrayNotHasKey('', $traffic);
        $this->assertArrayNotHasKey('ES', $traffic);
    }
}
