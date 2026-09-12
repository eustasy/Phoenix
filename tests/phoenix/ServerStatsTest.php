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
        require_once __DIR__.'/../../src/functions/server.stats.cores.php';

        $this->assertGreaterThanOrEqual(1, \server_stats_cores());
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
