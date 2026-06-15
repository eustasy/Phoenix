<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/model/events.geo.counts.php';

class EventsGeoCountsTest extends PhoenixTestCase
{
    // Sentinel info_hash + country codes that no real GeoLite2 lookup would
    // produce, so the assertions hold regardless of any other ledger rows.
    private const HASH = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'events` WHERE `info_hash` = \''.self::HASH.'\';',
        );
        parent::tearDown();
    }

    private function event(string $event, string $country): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'events` (`time`, `info_hash`, `event`, `country`) '.
            'VALUES ('.self::$time.', \''.self::HASH.'\', \''.$event.'\', \''.$country.'\');',
        );
    }

    public function testCountsCompletedByCountry(): void
    {
        $this->event('completed', 'ZZ');
        $this->event('completed', 'ZZ');
        $this->event('completed', 'XX');

        $counts = \events_geo_counts(self::$connection, self::$settings);
        $this->assertSame(2, $counts['ZZ'] ?? 0);
        $this->assertSame(1, $counts['XX'] ?? 0);
    }

    public function testExcludesNonCompletedAndUntaggedRows(): void
    {
        // A started event and a completed-but-untagged row must NOT be counted.
        $this->event('started', 'ZZ');
        $this->event('completed', '');

        $counts = \events_geo_counts(self::$connection, self::$settings);
        // The 'started' row doesn't lift ZZ, and the empty-country row is skipped.
        $this->assertSame(0, $counts['ZZ'] ?? 0);
        $this->assertArrayNotHasKey('', $counts);
    }
}
