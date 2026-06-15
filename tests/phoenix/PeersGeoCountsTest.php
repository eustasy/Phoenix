<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/model/peers.geo.counts.php';
require_once __DIR__.'/../../src/model/peer.insert.php'; // for insertPeer()

// The live IP→country lookup needs the geoip2 library + a GeoLite2 .mmdb, which
// CI does not provide, so these cover the gate: when geo isn't configured the
// model returns an empty map regardless of the peers present. The lookup itself
// is exercised by StatsGeoLookupTest.
class PeersGeoCountsTest extends PhoenixTestCase
{
    private const HASH = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    protected function setUp(): void
    {
        parent::setUp();
        // A peer with a real public IP, so only the geo gate can yield [].
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `ipv4`, `ipv6`, `portv4`, `portv6`, `uploaded`, `downloaded`, `left`, `state`, `updated`) '.
            'VALUES (\''.self::HASH.'\', \''.str_repeat('1', 40).'\', \'\', \'\', \'8.8.8.8\', \'\', 6881, 0, 0, 0, 0, 1, '.self::$time.');',
        );
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` = \''.self::HASH.'\';',
        );
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function settings(array $overrides): array
    {
        return array_merge(self::$settings, $overrides);
    }

    public function testReturnsEmptyWhenGeoDisabled(): void
    {
        $settings = $this->settings(['stats_geo' => false, 'stats_geo_database' => '/some/path.mmdb']);
        $this->assertSame([], \peers_geo_counts(self::$connection, $settings));
    }

    public function testReturnsEmptyWhenDatabaseUnreadable(): void
    {
        $settings = $this->settings([
            'stats_geo' => true,
            'stats_geo_database' => '/no/such/dir/'.bin2hex(random_bytes(6)).'.mmdb',
        ]);
        $this->assertSame([], \peers_geo_counts(self::$connection, $settings));
    }
}
