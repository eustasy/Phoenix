<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class StatsGeoDatabaseTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/stats.geo.database.php';
    }

    /** @var list<string> */
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

    public function testReturnsConfiguredPathWhenReadable(): void
    {
        // The explicit path is tried first, so this is deterministic regardless
        // of any system/config database present on the host.
        $path = (string) tempnam(sys_get_temp_dir(), 'phx_geo_');
        $this->tmp[] = $path;

        $this->assertSame($path, \stats_geo_database($this->settings(['stats_geo_database' => $path])));
    }

    public function testConfiguredUnreadablePathDoesNotResolveToItself(): void
    {
        // A configured-but-missing path never resolves to that path; it either
        // discovers a standard location or returns '' — never the dead path.
        $dead = '/no/such/dir/'.bin2hex(random_bytes(6)).'.mmdb';

        $this->assertNotSame($dead, \stats_geo_database($this->settings(['stats_geo_database' => $dead])));
    }
}
