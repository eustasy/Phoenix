<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminSettingsHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.settings.php';
    }

    /** @return array<string, mixed> */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'phoenix_version' => 'Phoenix Test v.0',
            'db_pass' => 'secretpass',
            'admin_password' => 'bcrypthashvalue',
            'api_keys' => ['*' => 'topsecretkey', 'alice' => 'alicekey'],
            'open_tracker' => true,
            'public_index' => false,
            'full_scrape' => false,
            'db_reset' => false,
            'announce_interval' => 1800,
            'trusted_proxies' => [],
        ], $overrides);
    }

    public function testMasksSecrets(): void
    {
        $html = view_admin_settings_html($this->settings(), true, false, 'tok');

        // The actual secret values must never appear.
        $this->assertStringNotContainsString('secretpass', $html);
        $this->assertStringNotContainsString('bcrypthashvalue', $html);
        $this->assertStringNotContainsString('topsecretkey', $html);
        $this->assertStringNotContainsString('alicekey', $html);
        // ...they are masked instead.
        $this->assertStringContainsString('********', $html);
        $this->assertStringContainsString('2 keys configured', $html);
    }

    public function testRendersEditFormsWhenWritable(): void
    {
        $html = view_admin_settings_html($this->settings(), true, false, 'tok');
        $this->assertStringContainsString('name="process" value="password"', $html);
        $this->assertStringContainsString('name="new_password"', $html);
        $this->assertStringContainsString('name="process" value="settings"', $html);
        $this->assertStringContainsString('name="open_tracker"', $html);
        $this->assertStringContainsString('name="csrf" value="tok"', $html);
    }

    public function testReflectsCurrentFlagState(): void
    {
        $html = view_admin_settings_html($this->settings(['open_tracker' => true, 'public_index' => false]), true, false, 'tok');
        // open_tracker is on → its checkbox is checked; public_index off → not.
        $this->assertMatchesRegularExpression('/name="open_tracker"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="public_index"[^>]*checked/', $html);
    }

    public function testRendersStatsFlags(): void
    {
        // With geo available, both stats toggles render and stats_geo is enabled.
        $html = view_admin_settings_html($this->settings(), true, false, 'tok', false, null, null, null, true);
        $this->assertStringContainsString('name="stats_enabled"', $html);
        $this->assertStringContainsString('name="stats_geo"', $html);
        $this->assertDoesNotMatchRegularExpression('/name="stats_geo"[^>]*disabled/', $html);
    }

    public function testGreysStatsGeoWhenUnavailable(): void
    {
        // Default geo_available=false → stats_geo is disabled with guidance,
        // while stats_enabled stays toggleable.
        $html = view_admin_settings_html($this->settings(), true, false, 'tok');
        $this->assertMatchesRegularExpression('/name="stats_geo"[^>]*disabled/', $html);
        $this->assertStringContainsString('needs the geoip2 library', $html);
        $this->assertDoesNotMatchRegularExpression('/name="stats_enabled"[^>]*disabled/', $html);
    }

    public function testFullScrapeWarning(): void
    {
        $html = view_admin_settings_html($this->settings(), true, false, 'tok');
        $this->assertStringContainsString('exposes every', $html);
    }

    public function testRendersTwoFactorEnableFormWhenDisabledAndAvailable(): void
    {
        $html = view_admin_settings_html(
            $this->settings(['admin_totp_secret' => '']),
            true,
            false,
            'tok',
            true,
            'JBSWY3DPEHPK3PXP',
            'data:image/png;base64,QQ==',
            'otpauth://totp/x',
        );
        $this->assertStringContainsString('Two-Factor Authentication', $html);
        $this->assertStringContainsString('name="process" value="totp_enable"', $html);
        $this->assertStringContainsString('JBSWY3DPEHPK3PXP', $html);
        $this->assertStringContainsString('src="data:image/png;base64,QQ=="', $html);
        $this->assertStringNotContainsString('name="process" value="totp_disable"', $html);
    }

    public function testRendersTwoFactorDisableFormWhenEnabled(): void
    {
        $html = view_admin_settings_html(
            $this->settings(['admin_totp_secret' => 'JBSWY3DPEHPK3PXP']),
            true,
            false,
            'tok',
            true,
        );
        $this->assertStringContainsString('name="process" value="totp_disable"', $html);
        $this->assertStringContainsString('is enabled', $html);
        $this->assertStringNotContainsString('name="process" value="totp_enable"', $html);
        // The enrolled secret is masked, never echoed back into the panel.
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $html);
    }

    public function testOmitsTwoFactorSectionWhenLibraryUnavailable(): void
    {
        $html = view_admin_settings_html($this->settings(['admin_totp_secret' => '']), true, false, 'tok', false);
        $this->assertStringNotContainsString('Two-Factor Authentication', $html);
    }

    public function testReadOnlyWhenNotWritable(): void
    {
        $html = view_admin_settings_html($this->settings(), false, false, 'tok');
        // The settings table still renders...
        $this->assertStringContainsString('ph-card-table', $html);
        // ...but no edit forms, and the not-writable note shows.
        $this->assertStringNotContainsString('name="process" value="password"', $html);
        $this->assertStringNotContainsString('name="process" value="settings"', $html);
        $this->assertStringContainsString('is not writable', $html);
    }

    public function testMarksSettingsNavActive(): void
    {
        $html = view_admin_settings_html($this->settings(), true, false, 'tok');
        $this->assertStringContainsString('href="?page=settings" class="is-active" aria-current="page"', $html);
    }
}
