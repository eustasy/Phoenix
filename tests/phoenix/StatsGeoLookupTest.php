<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class StatsGeoLookupTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/stats.geo.lookup.php';
    }

    /** @var array<int, string> temp files to remove in tearDown */
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
    private function settings(array $overrides): array
    {
        return array_merge(self::$settings, $overrides);
    }

    private const EMPTY = ['country' => '', 'continent' => ''];

    public function testDisabledReturnsEmptyCodes(): void
    {
        $settings = $this->settings(['stats_geo' => false, 'stats_geo_database' => '/some/path.mmdb']);
        $this->assertSame(self::EMPTY, stats_geo_lookup($settings, '8.8.8.8'));
    }

    public function testEnabledButMissingDatabasePathReturnsEmptyCodes(): void
    {
        $settings = $this->settings(['stats_geo' => true, 'stats_geo_database' => '']);
        $this->assertSame(self::EMPTY, stats_geo_lookup($settings, '8.8.8.8'));
    }

    public function testEnabledButUnreadablePathReturnsEmptyCodes(): void
    {
        $settings = $this->settings([
            'stats_geo' => true,
            'stats_geo_database' => '/no/such/dir/'.bin2hex(random_bytes(6)).'.mmdb',
        ]);
        $this->assertSame(self::EMPTY, stats_geo_lookup($settings, '8.8.8.8'));
    }

    public function testEmptyIpReturnsEmptyCodes(): void
    {
        // A readable file is present, but an empty IP short-circuits before any
        // reader work.
        $path = (string) tempnam(sys_get_temp_dir(), 'phx_geo_');
        $this->tmp[] = $path;
        $settings = $this->settings(['stats_geo' => true, 'stats_geo_database' => $path]);
        $this->assertSame(self::EMPTY, stats_geo_lookup($settings, ''));
    }

    public function testInvalidDatabaseFileIsCaughtAndReturnsEmptyCodes(): void
    {
        // The path exists and is readable but is NOT a valid .mmdb — the reader
        // throws and the catch(\Throwable) keeps geo from breaking the announce.
        $path = (string) tempnam(sys_get_temp_dir(), 'phx_geo_');
        $this->tmp[] = $path;
        file_put_contents($path, 'this is not a maxmind database');

        $settings = $this->settings(['stats_geo' => true, 'stats_geo_database' => $path]);

        $result = stats_geo_lookup($settings, '8.8.8.8');
        $this->assertSame(self::EMPTY, $result);
    }
}
