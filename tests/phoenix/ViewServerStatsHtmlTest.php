<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewServerStatsHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__.'/../../src/views/html.server.stats.php';
    }

    /** @return array{available: bool, percent: int|null, detail: string, note: string} */
    private function stat(int|null $percent, string $detail = '1 GB / 2 GB', string $note = ''): array
    {
        return ['available' => $percent !== null, 'percent' => $percent, 'detail' => $detail, 'note' => $note];
    }

    public function testRendersARowPerMetric(): void
    {
        $html = \view_server_stats_html([
            'cpu' => $this->stat(10), 'memory' => $this->stat(20),
            'swap' => $this->stat(30), 'disk' => $this->stat(40),
        ]);

        foreach (['CPU', 'Memory', 'Swap', 'Disk'] as $label) {
            $this->assertStringContainsString('>'.$label.'</span>', $html);
        }
        $this->assertStringContainsString('10%', $html);
        $this->assertStringContainsString('width:40%', $html);
    }

    public function testEachMetricGetsItsOwnColourClass(): void
    {
        $html = \view_server_stats_html([
            'cpu' => $this->stat(10), 'memory' => $this->stat(20),
            'swap' => $this->stat(30), 'disk' => $this->stat(40),
        ]);

        foreach (['cpu', 'memory', 'swap', 'disk'] as $key) {
            $this->assertStringContainsString('ph-srv--'.$key, $html);
        }
    }

    public function testDetailIsTheHoverText(): void
    {
        // The sidebar stays a glance; the specifics are a hover away.
        $html = \view_server_stats_html(['memory' => $this->stat(50, '4.0 GB / 8.0 GB')]);

        $this->assertStringContainsString('title="Memory', $html);
        $this->assertStringContainsString('4.0 GB / 8.0 GB"', $html);
    }

    public function testDisabledSwapShowsOffRatherThanVanishing(): void
    {
        // An absent gauge reads as a failure; "Off" is a configuration the
        // operator should be able to confirm at a glance.
        $html = \view_server_stats_html([
            'swap' => ['available' => true, 'percent' => null, 'detail' => 'No swap configured on this machine', 'note' => 'Off'],
        ]);

        $this->assertStringContainsString('>Swap</span>', $html);
        $this->assertStringContainsString('Off', $html);
        $this->assertStringContainsString('width:0%', $html);
    }

    public function testUnreadableMetricStillShowsItsRow(): void
    {
        $html = \view_server_stats_html([
            'cpu' => ['available' => false, 'percent' => null, 'detail' => 'Unavailable', 'note' => 'n/a'],
        ]);

        $this->assertStringContainsString('>CPU</span>', $html);
        $this->assertStringContainsString('n/a', $html);
    }

    public function testPressureOverridesTheMetricColour(): void
    {
        $warning = \view_server_stats_html(['disk' => $this->stat(80)]);
        $critical = \view_server_stats_html(['disk' => $this->stat(95)]);
        $calm = \view_server_stats_html(['disk' => $this->stat(40)]);

        $this->assertStringContainsString('is-warning', $warning);
        $this->assertStringContainsString('is-critical', $critical);
        $this->assertStringNotContainsString('is-warning', $calm);
        $this->assertStringNotContainsString('is-critical', $calm);
    }

    public function testBarClampsButTheFigureDoesNot(): void
    {
        // CPU is load over cores, so above 100 is real and means work is
        // queuing. The number must still say so once the bar has run out.
        $html = \view_server_stats_html(['cpu' => $this->stat(250, 'Load 4.00 over 1 core')]);

        $this->assertStringContainsString('250%', $html);
        $this->assertStringContainsString('width:100%', $html);
    }

    public function testNoStatsRendersNothing(): void
    {
        $this->assertSame('', \view_server_stats_html([]));
    }

    public function testDetailIsEscaped(): void
    {
        $html = \view_server_stats_html(['disk' => $this->stat(10, '<script>alert(1)</script>')]);

        $this->assertStringNotContainsString('<script>', $html);
    }
}
