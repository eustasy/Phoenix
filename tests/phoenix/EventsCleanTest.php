<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class EventsCleanTest extends PhoenixTestCase
{
    // Retention fixtures must NOT match the sentinel purge's __TEST_%
    // LIKE pattern (underscores are single-char wildcards), or the purge
    // would delete them regardless of retention and mask the behaviour
    // under test. They are cleaned explicitly in tearDown instead.
    private const OLD_HASH = 'RETAIN_EV_OLD_ROW';
    private const NEW_HASH = 'RETAIN_EV_NEW_ROW';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/events.clean.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'events`'.
            ' WHERE `info_hash` LIKE \'__TEST_%\''.
            ' OR `info_hash` IN (\''.self::OLD_HASH.'\', \''.self::NEW_HASH.'\');',
        );
    }

    private function insertEvent(string $info_hash, int $time): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'events` '.
            '(`time`, `info_hash`, `event`) VALUES ('.$time.', \''.$info_hash.'\', \'completed\');',
        );
    }

    private function countEvents(string $info_hash): int
    {
        $result = mysqli_query(
            self::$connection,
            'SELECT COUNT(*) FROM `'.self::$settings['db_prefix'].'events` '.
            'WHERE `info_hash` = \''.$info_hash.'\';',
        );
        $row = $result ? mysqli_fetch_row($result) : false;

        return (int) ($row[0] ?? 0);
    }

    /** @return array<string, mixed> */
    private function settingsWithRetention(int $days): array
    {
        $settings = self::$settings;
        $settings['stats_retention'] = $days;

        return $settings;
    }

    public function testPrunesEventsOlderThanRetention(): void
    {
        // 30-day retention: a 31-day-old event goes, a 29-day-old stays.
        $this->insertEvent(self::OLD_HASH, self::$time - (31 * 86400));
        $this->insertEvent(self::NEW_HASH, self::$time - (29 * 86400));

        $this->assertTrue(events_clean(self::$connection, $this->settingsWithRetention(30), self::$time));

        $this->assertSame(0, $this->countEvents(self::OLD_HASH));
        $this->assertSame(1, $this->countEvents(self::NEW_HASH));
    }

    public function testRetentionZeroKeepsOldEvents(): void
    {
        // 0 = keep forever: even an ancient row survives.
        $this->insertEvent(self::OLD_HASH, 1);

        $this->assertTrue(events_clean(self::$connection, $this->settingsWithRetention(0), self::$time));

        $this->assertSame(1, $this->countEvents(self::OLD_HASH));
    }

    public function testPurgesTestSentinelsRegardlessOfRetention(): void
    {
        $this->insertEvent('__TEST_EV_FRESH__', self::$time);

        $this->assertTrue(events_clean(self::$connection, $this->settingsWithRetention(0), self::$time));

        $this->assertSame(0, $this->countEvents('__TEST_EV_FRESH__'));
    }
}
