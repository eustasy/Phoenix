<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentsFilterAllowedTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrents.filter.allowed.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'aaaa%\' OR `info_hash` LIKE \'bbbb%\';',
        );
        parent::tearDown();
    }

    private function register(string $hash): void
    {
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES (?, \'T\', 0, 1, 0);',
            [$hash],
        );
    }

    private const A = 'aaaa00000000000000000000000000000000000a';
    private const B = 'aaaa00000000000000000000000000000000000b';
    private const MISSING = 'bbbb0000000000000000000000000000000000ff';

    public function testReturnsOnlyRegisteredHashes(): void
    {
        $this->register(self::A);

        $this->assertSame(
            [self::A],
            \torrents_filter_allowed(self::$connection, self::$settings, [self::A, self::MISSING]),
        );
    }

    public function testUnregisteredHashAloneYieldsNothing(): void
    {
        // The caller reads this as "not allowed" — announce rejects, scrape
        // errors rather than falling through to a full scrape.
        $this->assertSame(
            [],
            \torrents_filter_allowed(self::$connection, self::$settings, [self::MISSING]),
        );
    }

    public function testPreservesTheOrderAsked(): void
    {
        // A multi-hash scrape answers in the order it was asked, not the
        // order the database happened to return.
        $this->register(self::A);
        $this->register(self::B);

        $this->assertSame(
            [self::B, self::A],
            \torrents_filter_allowed(self::$connection, self::$settings, [self::B, self::A]),
        );
    }

    public function testDuplicatesCollapse(): void
    {
        // The scrape response is keyed by hash, so asking twice was always one
        // answer.
        $this->register(self::A);

        $this->assertSame(
            [self::A],
            \torrents_filter_allowed(self::$connection, self::$settings, [self::A, self::A, self::A]),
        );
    }

    public function testEmptyInputDoesNotQuery(): void
    {
        $this->assertSame([], \torrents_filter_allowed(self::$connection, self::$settings, []));
        // An empty string is not a hash and must not reach the IN list.
        $this->assertSame([], \torrents_filter_allowed(self::$connection, self::$settings, ['', '']));
    }

    public function testHashesAreBoundNotInterpolated(): void
    {
        // Every value is a bound parameter, so a hash carrying SQL is just a
        // hash that matches nothing.
        $this->register(self::A);

        $this->assertSame(
            [self::A],
            \torrents_filter_allowed(self::$connection, self::$settings, [self::A, "' OR '1'='1"]),
        );
    }
}
