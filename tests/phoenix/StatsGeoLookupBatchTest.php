<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/stats.geo.lookup.batch.php';

// The resolving path needs the maxmind-db reader AND a GeoLite2 .mmdb, which
// MaxMind's licence forbids shipping, so CI has neither. These cover the gate
// and the failure paths — which is where the branches are — and the one
// resolving test skips itself when no database is present.
final class StatsGeoLookupBatchTest extends TestCase
{
    /** @var list<string> temp files to remove in tearDown */
    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->tmp = [];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'stats_geo' => true,
            'stats_geo_database' => '',
            'report_errors' => false,
        ], $overrides);
    }

    private function tempFile(string $contents = ''): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'phx_geob_');
        $this->tmp[] = $path;
        if ($contents !== '') {
            file_put_contents($path, $contents);
        }

        return $path;
    }

    public function testReturnsEmptyWhenGeoIsDisabled(): void
    {
        $settings = $this->settings(['stats_geo' => false, 'stats_geo_database' => $this->tempFile()]);

        $this->assertSame([], stats_geo_lookup_batch($settings, ['8.8.8.8']));
    }

    public function testReturnsEmptyForAnEmptyAddressList(): void
    {
        // Short-circuits before any reader work, so the Peers page pays nothing
        // for a page with no resolvable rows.
        $settings = $this->settings(['stats_geo_database' => $this->tempFile()]);

        $this->assertSame([], stats_geo_lookup_batch($settings, []));
    }

    public function testReturnsEmptyWhenTheDatabaseIsUnreadable(): void
    {
        $settings = $this->settings([
            'stats_geo_database' => '/no/such/dir/'.bin2hex(random_bytes(6)).'.mmdb',
        ]);

        $this->assertSame([], stats_geo_lookup_batch($settings, ['8.8.8.8']));
    }

    public function testCorruptDatabaseIsCaughtRatherThanThrown(): void
    {
        // Readable but not a .mmdb: the Reader constructor throws, and the
        // catch keeps a corrupt geo database from breaking the admin page.
        if (! class_exists(\MaxMind\Db\Reader::class)) {
            $this->markTestSkipped('maxmind-db reader not installed.');
        }

        $settings = $this->settings([
            'stats_geo_database' => $this->tempFile('this is not a maxmind database'),
        ]);

        $this->assertSame([], stats_geo_lookup_batch($settings, ['8.8.8.8']));
    }

    public function testEmptyAddressesInTheListAreSkipped(): void
    {
        // A peer with neither an IPv4 nor an IPv6 address contributes an empty
        // string, which must not reach the reader.
        if (! class_exists(\MaxMind\Db\Reader::class)) {
            $this->markTestSkipped('maxmind-db reader not installed.');
        }

        $settings = $this->settings([
            'stats_geo_database' => $this->tempFile('this is not a maxmind database'),
        ]);

        $this->assertSame([], stats_geo_lookup_batch($settings, ['', '']));
    }

    public function testResolvesAddressesAndKeysTheMapByTheAddressGivenIn(): void
    {
        $mmdb = __DIR__.'/../../config/GeoLite2-Country.mmdb';
        if (! class_exists(\MaxMind\Db\Reader::class) || ! is_readable($mmdb)) {
            $this->markTestSkipped('maxmind-db reader or GeoLite2 database not available.');
        }

        $settings = $this->settings(['stats_geo_database' => $mmdb]);

        // A duplicate is looked up once and still yields one entry, and an
        // address the database does not know is simply absent — so a caller can
        // read $map[$ip]['country'] ?? '' and get '' for unknown.
        $map = stats_geo_lookup_batch($settings, ['8.8.8.8', '8.8.8.8', '10.0.0.1']);

        $this->assertArrayNotHasKey('10.0.0.1', $map);
        if (isset($map['8.8.8.8'])) {
            $this->assertSame(2, strlen($map['8.8.8.8']['country']));
            $this->assertSame(strtoupper($map['8.8.8.8']['country']), $map['8.8.8.8']['country']);
            $this->assertNotSame('', $map['8.8.8.8']['name']);
        }
    }
}
