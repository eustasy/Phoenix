<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class EventInsertTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/event.insert.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'events` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{time: int, info_hash: string, event: string, client: string, user: string, country: string, continent: string}
     */
    private function event(array $overrides = []): array
    {
        return array_merge([
            'time' => self::$time,
            'info_hash' => '__TEST_EVENT_1__',
            'event' => 'completed',
            'client' => 'qBittorrent 4.6.2.0',
            'user' => 'owner',
            'country' => 'US',
            'continent' => 'NA',
        ], $overrides);
    }

    /** @return array<string, string|null>|false */
    private function fetchRow(string $info_hash): array|false
    {
        $result = mysqli_query(
            self::$connection,
            'SELECT `time`, `info_hash`, `event`, `client`, `user`, `country`, `continent` '.
            'FROM `'.self::$settings['db_prefix'].'events` '.
            'WHERE `info_hash` = \''.$info_hash.'\';',
        );

        return $result ? (mysqli_fetch_assoc($result) ?: false) : false;
    }

    public function testInsertsAndReadsBack(): void
    {
        $this->assertTrue(event_insert(self::$connection, self::$settings, $this->event()));

        $row = $this->fetchRow('__TEST_EVENT_1__');
        $this->assertIsArray($row);
        $this->assertEquals(self::$time, $row['time']);
        $this->assertSame('__TEST_EVENT_1__', $row['info_hash']);
        $this->assertSame('completed', $row['event']);
        $this->assertSame('qBittorrent 4.6.2.0', $row['client']);
        $this->assertSame('owner', $row['user']);
        $this->assertSame('US', $row['country']);
        $this->assertSame('NA', $row['continent']);
    }

    public function testEmptyGeoCodesStoreAsEmptyStrings(): void
    {
        $this->assertTrue(event_insert(self::$connection, self::$settings, $this->event([
            'country' => '',
            'continent' => '',
        ])));

        $row = $this->fetchRow('__TEST_EVENT_1__');
        $this->assertIsArray($row);
        $this->assertSame('', $row['country']);
        $this->assertSame('', $row['continent']);
    }
}
