<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminGeographyHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.geography.php';
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return ['phoenix_version' => 'Phoenix Test v.0', 'admin_password' => 'hash'];
    }

    public function testTrafficMetricRendersAsSizesNotRawByteCounts(): void
    {
        $html = view_admin_geography_html(
            $this->settings(),
            ['downloads' => ['BR' => 157875], 'traffic' => ['BR' => 293437328142336]],
            'tok',
        );

        $this->assertStringContainsString('data-metric="traffic"', $html);
        // The script formats byte metrics as sizes; without the flag the panel
        // would read 293437328142336.
        $this->assertStringContainsString('"format":"bytes"', $html);
    }

    public function testMetricCanBeDeepLinked(): void
    {
        // The Traffic page links here for the geographic cut; without this it
        // would land on whichever metric happened to be first.
        $metrics = ['peers' => ['GB' => 5], 'traffic' => ['BR' => 1024]];

        $this->assertStringContainsString(
            'GEO_DEFAULT = "traffic"',
            view_admin_geography_html($this->settings(), $metrics, 'tok', 'traffic'),
        );
        // An unknown metric falls back rather than rendering an empty map.
        $this->assertStringContainsString(
            'GEO_DEFAULT = "peers"',
            view_admin_geography_html($this->settings(), $metrics, 'tok', 'nonsense'),
        );
    }

    public function testNotConfiguredStateWhenNoMetrics(): void
    {
        $html = view_admin_geography_html($this->settings(), [], 'tok');
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Geography</title>', $html);
        $this->assertStringContainsString('<a href="?page=geography" class="is-active" aria-current="page">', $html);
        // Guidance on how to populate it, and no map.
        $this->assertStringContainsString("isn't available yet", $html);
        $this->assertStringContainsString('stats_geo', $html);
        $this->assertStringNotContainsString('id="geo-map"', $html);
    }

    public function testRendersPeersMetric(): void
    {
        $html = view_admin_geography_html($this->settings(), ['peers' => ['US' => 10, 'DE' => 5]], 'tok');
        $this->assertStringContainsString('id="geo-map"', $html);
        $this->assertStringContainsString('jsvectormap', $html);
        // The metric toggle and the data both render.
        $this->assertStringContainsString('data-metric="peers"', $html);
        $this->assertStringContainsString('Active peers', $html);
        $this->assertStringContainsString('"US":10', $html);
        $this->assertStringContainsString('<a href="?page=geography" class="is-active" aria-current="page">', $html);
    }

    public function testRendersBothMetricsWhenSupplied(): void
    {
        $html = view_admin_geography_html(
            $this->settings(),
            ['peers' => ['US' => 1], 'downloads' => ['DE' => 2]],
            'tok',
        );
        $this->assertStringContainsString('data-metric="peers"', $html);
        $this->assertStringContainsString('data-metric="downloads"', $html);
        $this->assertStringContainsString('Active peers', $html);
        $this->assertStringContainsString('Completed downloads', $html);
        // Default metric is the first supplied.
        $this->assertStringContainsString('GEO_DEFAULT = "peers"', $html);
    }

    public function testRendersMetricWithNoDataYet(): void
    {
        // A configured-but-empty metric (e.g. geo on, no completions geo-tagged
        // yet) still gets its toggle and an empty-state hint, not omission.
        $html = view_admin_geography_html($this->settings(), ['peers' => ['US' => 5], 'downloads' => []], 'tok');
        $this->assertStringContainsString('data-metric="downloads"', $html);
        $this->assertStringContainsString('No data for this metric yet', $html);
    }

    public function testDownloadsOnlyWhenPeersUnavailable(): void
    {
        // geoip2 missing but the ledger has geo data → only the downloads metric.
        $html = view_admin_geography_html($this->settings(), ['downloads' => ['GB' => 3]], 'tok');
        $this->assertStringContainsString('data-metric="downloads"', $html);
        $this->assertStringNotContainsString('data-metric="peers"', $html);
        $this->assertStringContainsString('GEO_DEFAULT = "downloads"', $html);
    }
}
