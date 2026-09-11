<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class EventsClientBreakdownTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/events.client.breakdown.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'events` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    private function logCompletion(string $client, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            mysqli_execute_query(
                self::$connection,
                'INSERT INTO `'.self::$settings['db_prefix'].'events` '.
                '(`time`, `info_hash`, `event`, `client`) VALUES (?, ?, \'completed\', ?);',
                [time(), '__TEST_ecb__', $client],
            );
        }
    }

    public function testGroupsLabelsIntoFamiliesAndVersions(): void
    {
        $this->logCompletion('Transmission 4.1.3.0', 3);
        $this->logCompletion('Transmission 3.0.0', 1);

        $families = \events_client_breakdown(self::$connection, self::$settings);

        $this->assertArrayHasKey('Transmission', $families);
        $this->assertSame(3, $families['Transmission']['4.1.3.0']);
        $this->assertSame(1, $families['Transmission']['3.0.0']);
    }

    public function testRepairsTheMangledMicroSignOnUTorrent(): void
    {
        // Rows written before the column held UTF-8 carry '?Torrent', where the
        // 0xB5 became 0x3F. Both spellings have to land in one family rather
        // than sorting apart alphabetically.
        $this->logCompletion('?Torrent 3.5.5', 2);
        $this->logCompletion('µTorrent 3.5.5', 1);

        $families = \events_client_breakdown(self::$connection, self::$settings);

        $this->assertArrayHasKey('µTorrent', $families);
        $this->assertArrayNotHasKey('?Torrent', $families);
        $this->assertSame(3, $families['µTorrent']['3.5.5']);
    }

    public function testLabelWithNoVersionBecomesAnEmptyVersionKey(): void
    {
        // An unrecognised client still has to chart as one solid bar.
        $this->logCompletion('Unknown', 2);

        $families = \events_client_breakdown(self::$connection, self::$settings);

        $this->assertSame(2, $families['Unknown']['']);
    }

    public function testFamiliesComeBackBiggestFirst(): void
    {
        $this->logCompletion('__TEST_Big 1.0', 5);
        $this->logCompletion('__TEST_Small 1.0', 1);

        $families = \events_client_breakdown(self::$connection, self::$settings);
        $ours = array_values(array_filter(
            array_keys($families),
            static fn (string $f): bool => str_starts_with($f, '__TEST_'),
        ));

        $this->assertSame(['__TEST_Big', '__TEST_Small'], $ours);
    }

    public function testIgnoresEventsThatAreNotCompletions(): void
    {
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'events` '.
            '(`time`, `info_hash`, `event`, `client`) VALUES (?, ?, \'started\', ?);',
            [time(), '__TEST_ecb__', '__TEST_Started 1.0'],
        );

        $this->assertArrayNotHasKey('__TEST_Started', \events_client_breakdown(self::$connection, self::$settings));
    }
}
