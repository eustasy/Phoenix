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

    public function testInvalidDatabaseIsReported(): void
    {
        // A readable-but-invalid .mmdb throws InvalidDatabaseException (not
        // AddressNotFound): with report_errors on it must fire the error hook,
        // so a corrupt geo database is not silently masked as "no geo data".
        if (! class_exists(\GeoIp2\Database\Reader::class)) {
            $this->markTestSkipped('geoip2 library not installed.');
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'phx_geo_');
        $this->tmp[] = $path;
        file_put_contents($path, 'this is not a maxmind database');
        $settings = $this->settings([
            'stats_geo' => true,
            'stats_geo_database' => $path,
            'report_errors' => true,
        ]);

        $marker = $this->withMarkerErrorHook();
        try {
            $this->assertSame(self::EMPTY, stats_geo_lookup($settings, '8.8.8.8'));
            $this->assertSame('stats_geo_lookup', (string) file_get_contents($marker));
        } finally {
            $this->restoreErrorHook();
        }
    }

    public function testNormalLookupIsNotReported(): void
    {
        // A normal lookup — whether the IP resolves or is simply not found
        // (AddressNotFound) — must NOT fire the error hook; only corrupt-DB /
        // library faults do. Needs a real GeoLite2 database, which MaxMind's
        // licence forbids shipping, so this skips when it is absent.
        $mmdb = __DIR__.'/../../config/GeoLite2-Country.mmdb';
        if (! class_exists(\GeoIp2\Database\Reader::class) || ! is_readable($mmdb)) {
            $this->markTestSkipped('geoip2 library or GeoLite2 database not available.');
        }

        $settings = $this->settings([
            'stats_geo' => true,
            'stats_geo_database' => $mmdb,
            'report_errors' => true,
        ]);

        $marker = $this->withMarkerErrorHook();
        try {
            // A private address is a normal "not found" lookup.
            stats_geo_lookup($settings, '10.0.0.1');
            $this->assertSame('', (string) file_get_contents($marker), 'a normal lookup must not fire the error hook');
        } finally {
            $this->restoreErrorHook();
        }
    }

    private string $hookBackup = '';

    /** Swap the shipped error hook for a marker-writer; returns the marker path. */
    private function withMarkerErrorHook(): string
    {
        $hookPath = __DIR__.'/../../src/hooks/phoenix.error.php';
        $this->hookBackup = $hookPath.'.audit-bak';
        $marker = (string) tempnam(sys_get_temp_dir(), 'phx_geo_marker_');
        $this->tmp[] = $marker;
        $this->assertTrue(rename($hookPath, $this->hookBackup));
        file_put_contents($hookPath, "<?php\n\nfile_put_contents(".var_export($marker, true).", (\$context['source'] ?? ''));\n");

        return $marker;
    }

    private function restoreErrorHook(): void
    {
        $hookPath = __DIR__.'/../../src/hooks/phoenix.error.php';
        if ($this->hookBackup !== '' && is_file($this->hookBackup)) {
            unlink($hookPath);
            rename($this->hookBackup, $hookPath);
            $this->hookBackup = '';
        }
    }
}
