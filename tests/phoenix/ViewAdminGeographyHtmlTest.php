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
            'traffic',
            ['BR' => 293437328142336],
            ['downloads', 'traffic'],
            'tok',
        );

        $this->assertStringContainsString('metric=traffic', $html);
        // The script formats byte metrics as sizes; without the flag the panel
        // would read 293437328142336.
        $this->assertStringContainsString('"format":"bytes"', $html);
    }

    public function testOnlyTheSelectedMetricIsInlined(): void
    {
        // Each metric reads the whole events ledger, so the page computes one
        // and links to the rest rather than shipping all of them for a
        // client-side toggle.
        $html = view_admin_geography_html(
            $this->settings(),
            'traffic',
            ['BR' => 1024],
            ['peers', 'downloads', 'traffic'],
            'tok',
        );

        preg_match('/var GEO = (\{.*?\});\n/s', $html, $m);
        $this->assertNotEmpty($m, 'GEO should be inlined');
        $geo = json_decode($m[1], true);
        $this->assertSame(['traffic'], array_keys($geo));
    }

    public function testEveryAvailableMetricIsOfferedAsALink(): void
    {
        // Links, not buttons: each metric is its own request and its own URL.
        $html = view_admin_geography_html(
            $this->settings(),
            'peers',
            ['GB' => 5],
            ['peers', 'downloads', 'traffic'],
            'tok',
        );

        $this->assertSame(3, substr_count($html, '<a class="seg-btn'));
        $this->assertStringContainsString('?page=geography&amp;metric=downloads', $html);
        $this->assertStringContainsString('seg-btn is-on', $html);
    }

    public function testUnknownMetricFallsBackToTheFirstAvailable(): void
    {
        $html = view_admin_geography_html(
            $this->settings(),
            'nonsense',
            [],
            ['peers', 'downloads'],
            'tok',
        );

        $this->assertStringContainsString('GEO_DEFAULT = "peers"', $html);
    }

    public function testNotConfiguredStateWhenNothingIsAvailable(): void
    {
        $html = view_admin_geography_html($this->settings(), '', [], [], 'tok');
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Geography</title>', $html);
        // Guidance on how to populate it, and no map.
        $this->assertStringContainsString("isn't available yet", $html);
        $this->assertStringContainsString('stats_geo', $html);
        $this->assertStringNotContainsString('id="geo-map"', $html);
    }

    public function testRendersPeersMetric(): void
    {
        $html = view_admin_geography_html($this->settings(), 'peers', ['US' => 10, 'DE' => 5], ['peers'], 'tok');
        $this->assertStringContainsString('id="geo-map"', $html);
        $this->assertStringContainsString('jsvectormap', $html);
        $this->assertStringContainsString('Active peers', $html);
        $this->assertStringContainsString('"US":10', $html);
        $this->assertStringContainsString('<a href="?page=geography" class="is-active" aria-current="page">', $html);
    }

    public function testRendersMetricWithNoDataYet(): void
    {
        // A configured-but-empty metric (geo on, nothing geo-tagged yet) still
        // renders its map and an empty-state hint, not omission.
        $html = view_admin_geography_html($this->settings(), 'downloads', [], ['peers', 'downloads'], 'tok');
        $this->assertStringContainsString('metric=downloads', $html);
        $this->assertStringContainsString('GEO_DEFAULT = "downloads"', $html);
    }

    public function testDownloadsOnlyWhenPeersUnavailable(): void
    {
        // geoip2 missing but the ledger has geo data → no peers segment.
        $html = view_admin_geography_html($this->settings(), 'downloads', ['GB' => 3], ['downloads', 'traffic'], 'tok');
        $this->assertStringNotContainsString('metric=peers', $html);
        $this->assertStringContainsString('GEO_DEFAULT = "downloads"', $html);
    }
}
