<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.php';
    }

    /** @return array<string, mixed> */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'phoenix_version' => 'Phoenix Test v.0',
            'admin_password' => '',
        ], $overrides);
    }

    /** @return array<string, int> */
    private function stats(): array
    {
        return [
            'seeders' => 3, 'leechers' => 2, 'peers' => 5,
            'torrents' => 4, 'downloads' => 10, 'traffic' => 123456, 'registered' => 7,
        ];
    }

    public function testRendersBaseDocument(): void
    {
        $html = view_admin_html($this->settings(), true);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Dashboard</title>', $html);
        $this->assertStringContainsString('Phoenix Test v.0', $html);
    }

    public function testShowsLogoutFormWhenAdminPasswordSet(): void
    {
        // Logout form only appears when auth is configured; otherwise there's
        // nothing to log out of and rendering the form would be confusing.
        $withPassword = view_admin_html($this->settings(['admin_password' => 'hash']), true, false, 'tok');
        $this->assertStringContainsString('name="logout" value="1"', $withPassword);
        $this->assertStringContainsString('Log out', $withPassword);

        $noPassword = view_admin_html($this->settings(), true);
        $this->assertStringNotContainsString('name="logout"', $noPassword);
    }

    public function testShowsInstalledBannerWhenFlagged(): void
    {
        // admin.php sets show_installed=true after redirecting from the
        // installer so the user sees confirmation on first load.
        $html = view_admin_html($this->settings(), true, true);
        $this->assertStringContainsString('Installation complete.', $html);
    }

    public function testRendersStatsBlockWhenStatsProvided(): void
    {
        // When the dashboard controller supplies stats, the overview renders
        // with number_format()-ed figures and last-run lines.
        $tasks = [
            'clean' => ['value' => 1700000000, 'source' => 'cron'],
            'optimize' => ['value' => 1700000100, 'source' => 'admin'],
        ];
        $html = view_admin_html($this->settings(), true, false, '', $this->stats(), $tasks);

        // Stat cards carry the headline figures.
        $this->assertStringContainsString('Active peers', $html);
        $this->assertStringContainsString('<div class="ph-stat-value">5</div>', $html);
        $this->assertStringContainsString('Registered torrents', $html);
        $this->assertStringContainsString('<div class="ph-stat-value">7</div>', $html);
        $this->assertStringContainsString('with active peers', $html);
        $this->assertStringContainsString('Traffic served', $html);
        $this->assertStringContainsString('123,456 bytes', $html);
        // Maintenance rows render only for tasks that have run, with a By column
        // naming who ran each (capitalised source).
        $this->assertStringContainsString('Cleaned', $html);
        $this->assertStringContainsString('Optimized', $html);
        $this->assertStringNotContainsString('Migrated', $html);
        $this->assertStringContainsString('<th>By</th>', $html);
        $this->assertStringContainsString('>Cron</span>', $html);
        $this->assertStringContainsString('>Admin</span>', $html);
    }

    public function testShowsNotInstalledNoticeWhenTablesMissing(): void
    {
        // No stats and no tables → point the operator at Utilities/Support.
        $html = view_admin_html($this->settings(), false);
        $this->assertStringContainsString('database is not installed yet', $html);
        $this->assertStringContainsString('?page=utilities', $html);
        $this->assertStringContainsString('?page=support', $html);
    }

    public function testShowsNoStatsNoticeWhenInstalledButNoStats(): void
    {
        // Tables present but no aggregated stats yet → a neutral notice rather
        // than the not-installed warning.
        $html = view_admin_html($this->settings(), true);
        $this->assertStringContainsString('No tracker statistics yet.', $html);
    }

    public function testOmitsMaintenanceFormsAndDiagnostics(): void
    {
        // Setup/clean/optimize/migrate, the add-torrent form, and the
        // compatibility diagnostics all moved to their own pages; the
        // dashboard is now a read-only overview.
        $html = view_admin_html($this->settings(), true, false, '', $this->stats(), []);
        $this->assertStringNotContainsString('name="process" value="setup"', $html);
        $this->assertStringNotContainsString('name="process" value="clean"', $html);
        $this->assertStringNotContainsString('name="process" value="torrent_add"', $html);
        $this->assertStringNotContainsString('Compatibility Check', $html);
        $this->assertStringNotContainsString('Your server supports MySQL.', $html);
    }
}
