<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ServerStatsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__.'/../../src/functions/server.stats.php';
    }

    public function testReturnsAnEntryForEveryMetric(): void
    {
        // Every metric reports, whether or not the host let it be read — a
        // missing key would make the sidebar drop a row silently.
        $stats = \server_stats();

        foreach (['cpu', 'memory', 'swap', 'disk'] as $key) {
            $this->assertArrayHasKey($key, $stats);
            $this->assertArrayHasKey('available', $stats[$key]);
            $this->assertArrayHasKey('percent', $stats[$key]);
            $this->assertArrayHasKey('detail', $stats[$key]);
            $this->assertArrayHasKey('note', $stats[$key]);
        }
    }

    public function testPercentagesAreIntsOrNull(): void
    {
        foreach (\server_stats() as $key => $stat) {
            if ($stat['percent'] !== null) {
                $this->assertIsInt($stat['percent'], $key);
                $this->assertGreaterThanOrEqual(0, $stat['percent'], $key);
            }
        }
    }

    public function testAnUnavailableMetricCarriesNoPercentage(): void
    {
        // Holds on any host: a metric that could not be read reports no figure
        // and says why, and one that could reports a note only when it has
        // something to say instead of a percentage (swap that is switched off).
        foreach (\server_stats() as $key => $stat) {
            if ($stat['available']) {
                $this->assertTrue($stat['percent'] !== null || $stat['note'] !== '', $key);
                continue;
            }
            $this->assertNull($stat['percent'], $key);
            $this->assertNotSame('', $stat['note'], $key);
        }
    }

    public function testDiskMeasuresTheInstallNotTheWholeMachine(): void
    {
        // The partition that stops the tracker when it fills is the one holding
        // the install and its backups.
        $stats = \server_stats();
        if (! $stats['disk']['available']) {
            $this->markTestSkipped('disk_free_space() unavailable on this host');
        }

        $this->assertStringContainsString('/', $stats['disk']['detail']);
        $this->assertLessThanOrEqual(100, $stats['disk']['percent']);
    }

    public function testCoresIsAtLeastOne(): void
    {
        // Falls back to 1 rather than 0 — dividing load by zero would be worse
        // than over-reporting on a host that hides /proc.
        require_once __DIR__.'/../../src/functions/server.stats.cpuinfo.php';

        $this->assertGreaterThanOrEqual(1, \server_stats_cpuinfo()['cores']);
    }

    public function testClockAndCacheAreNullableNotZero(): void
    {
        // Plenty of kernels and containers report no `cpu MHz` or `cache size`.
        // A zero there would read as a measurement rather than a silence, so
        // the hover omits what it does not have.
        require_once __DIR__.'/../../src/functions/server.stats.cpuinfo.php';
        $cpu = \server_stats_cpuinfo();

        foreach (['mhz', 'cache'] as $key) {
            if ($cpu[$key] !== null) {
                $this->assertGreaterThan(0, $cpu[$key], $key);
            } else {
                $this->assertNull($cpu[$key], $key);
            }
        }
    }

    public function testCacheIsTheLastLevelAndKnowsWhichLevelThatIs(): void
    {
        // /proc/cpuinfo's `cache size` cannot be interpreted without the
        // vendor — on a Ryzen it is the per-pair L2, not the L3 that anyone
        // means by "CPU cache" — so the figure comes from /sys with its level.
        require_once __DIR__.'/../../src/functions/server.stats.cpuinfo.php';
        $cpu = \server_stats_cpuinfo();

        if ($cpu['cache'] === null) {
            $this->assertNull($cpu['cache_level']);
            $this->markTestSkipped('/sys cache hierarchy unavailable on this host');
        }

        $this->assertNotNull($cpu['cache_level']);
        $this->assertGreaterThanOrEqual(1, $cpu['cache_level']);
        // The last level, so never L1 on a machine that reports more than one.
        $this->assertGreaterThan(0, $cpu['cache']);
    }

    public function testCpuDetailPutsHardwareOnItsOwnLine(): void
    {
        $stats = \server_stats();
        if (! $stats['cpu']['available']) {
            $this->markTestSkipped('sys_getloadavg() unavailable on this host');
        }

        // Load on the first line, the hardware it is measured against on the
        // second, so the hover reads as two facts rather than one long one.
        $lines = explode("\n", $stats['cpu']['detail']);
        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('Load ', $lines[0]);
        $this->assertMatchesRegularExpression('/^\d+ cores?/', $lines[1]);
    }

    public function testMeminfoValuesAreBytes(): void
    {
        require_once __DIR__.'/../../src/functions/server.stats.meminfo.php';
        $meminfo = \server_stats_meminfo();

        if ($meminfo === []) {
            $this->markTestSkipped('/proc/meminfo unavailable on this host');
        }
        // /proc reports kB; anything sane is far more than that once scaled.
        $this->assertArrayHasKey('MemTotal', $meminfo);
        $this->assertGreaterThan(1048576, $meminfo['MemTotal']);
    }
}
