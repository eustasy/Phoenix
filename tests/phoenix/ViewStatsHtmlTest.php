<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewStatsHtmlTest extends TestCase
{
    public function testRenderHtml()
    {
        require_once __DIR__.'/../../src/views/html.stats.php';

        $stats = [
            'peers' => 15,
            'seeders' => 10,
            'leechers' => 5,
            'torrents' => 3,
            'downloads' => 100,
            'traffic' => 5000000,
        ];
        $settings = ['phoenix_version' => '1.0.0'];

        $output = view_stats_html($stats, $settings);

        $this->assertStringStartsWith('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('<h1>Tracker Stats</h1>', $output);
        // Hero peer count + seeder/leecher breakdown.
        $this->assertStringContainsString('<div class="stats-hero-num">15</div>', $output);
        $this->assertStringContainsString('<div class="b-num seed">10</div>', $output);
        $this->assertStringContainsString('<div class="b-num leech">5</div>', $output);
        // Cards: torrents-with-peers, completed downloads, traffic.
        $this->assertStringContainsString('<div class="ph-stat-value">3</div>', $output);
        $this->assertStringContainsString('<div class="ph-stat-value">100</div>', $output);
        $this->assertStringContainsString('4.8 MB', $output);
        $this->assertStringContainsString('5,000,000 bytes', $output);
        // Version flows into the footer.
        $this->assertStringContainsString('1.0.0', $output);
    }

    public function testRenderHtmlWithZeroStats()
    {
        require_once __DIR__.'/../../src/views/html.stats.php';

        $stats = [
            'peers' => 0,
            'seeders' => 0,
            'leechers' => 0,
            'torrents' => 0,
            'downloads' => 0,
            'traffic' => 0,
        ];
        $settings = ['phoenix_version' => '1.0.0'];

        $output = view_stats_html($stats, $settings);

        $this->assertStringContainsString('<div class="stats-hero-num">0</div>', $output);
        $this->assertStringContainsString('0 B', $output);
        $this->assertStringContainsString('0 bytes', $output);
        // An empty swarm collapses the split bar to zero width.
        $this->assertStringContainsString('width:0%', $output);
    }

    public function testRenderHtmlFormatsLargeNumbers()
    {
        require_once __DIR__.'/../../src/views/html.stats.php';

        $stats = [
            'peers' => 1234567,
            'seeders' => 654321,
            'leechers' => 580246,
            'torrents' => 9876,
            'downloads' => 543210,
            'traffic' => 9876543210,
        ];
        $settings = ['phoenix_version' => '1.0.0'];

        $output = view_stats_html($stats, $settings);

        // number_format() thousands separators throughout.
        $this->assertStringContainsString('<div class="stats-hero-num">1,234,567</div>', $output);
        $this->assertStringContainsString('<div class="b-num seed">654,321</div>', $output);
        $this->assertStringContainsString('<div class="b-num leech">580,246</div>', $output);
        $this->assertStringContainsString('<div class="ph-stat-value">9,876</div>', $output);
        $this->assertStringContainsString('<div class="ph-stat-value">543,210</div>', $output);
        // Traffic: human-readable headline + exact bytes.
        $this->assertStringContainsString('9.2 GB', $output);
        $this->assertStringContainsString('9,876,543,210 bytes', $output);
    }
}
