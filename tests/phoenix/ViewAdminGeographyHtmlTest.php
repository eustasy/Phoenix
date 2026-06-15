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

    public function testRendersBaseDocument(): void
    {
        $html = view_admin_geography_html($this->settings(), 'tok');
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Geography</title>', $html);
        $this->assertStringContainsString('<a href="?page=geography" class="is-active" aria-current="page">', $html);
    }

    public function testFlagsItAsUnwiredPreview(): void
    {
        // The page is UI-only for now; the banner must say so plainly.
        $html = view_admin_geography_html($this->settings(), 'tok');
        $this->assertStringContainsString('Preview', $html);
        $this->assertStringContainsString('not wired to the tracker', $html);
    }

    public function testRendersMapAndMetricToggle(): void
    {
        $html = view_admin_geography_html($this->settings(), 'tok');
        $this->assertStringContainsString('id="geo-map"', $html);
        $this->assertStringContainsString('data-metric="peers"', $html);
        $this->assertStringContainsString('data-metric="traffic"', $html);
        // The map library is loaded for this page.
        $this->assertStringContainsString('jsvectormap', $html);
    }
}
