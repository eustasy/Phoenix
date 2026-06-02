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
            'db_reset' => false,
        ], $overrides);
    }

    public function testRendersBaseDocument(): void
    {
        $html = view_admin_html($this->settings(), true, false);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Diagnostics and Utilities</title>', $html);
        $this->assertStringContainsString('Phoenix Test v.0', $html);
    }

    public function testShowsLogoutFormWhenAdminPasswordSet(): void
    {
        // Logout form only appears when auth is configured; otherwise there's
        // nothing to log out of and rendering the form would be confusing.
        $withPassword = view_admin_html($this->settings(['admin_password' => 'hash']), true, false);
        $this->assertStringContainsString('name="logout" value="1"', $withPassword);
        $this->assertStringContainsString('Log out', $withPassword);

        $noPassword = view_admin_html($this->settings(), true, false);
        $this->assertStringNotContainsString('name="logout"', $noPassword);
    }

    public function testShowsInstalledBannerWhenFlagged(): void
    {
        // admin.php sets show_installed=true after redirecting from the
        // installer so the user sees confirmation on first load.
        $html = view_admin_html($this->settings(), true, false, false, true);
        $this->assertStringContainsString('Installation complete.', $html);
    }

    public function testShowsTablesInstalledWithSize(): void
    {
        $html = view_admin_html(
            $this->settings(),
            true,
            ['Data' => 100, 'Indexes' => 50, 'Total' => 1234567, 'Free' => 0],
        );
        $this->assertStringContainsString('All your tables are installed.', $html);
        $this->assertStringContainsString('1,234,567 bytes', $html);
    }

    public function testShowsTablesMissingWarningWhenNotInstalled(): void
    {
        $html = view_admin_html($this->settings(), false, false);
        $this->assertStringContainsString('Some or all of your tables are not installed.', $html);
        // Setup form must render (so the user can install) regardless of db_reset.
        $this->assertStringContainsString('name="process" value="setup"', $html);
    }

    public function testHidesSetupFormWhenInstalledAndResetDisabled(): void
    {
        // db_reset=false + tables_installed=true means setup is locked; the
        // admin should see a "Disabled" badge instead of an actionable button.
        $html = view_admin_html($this->settings(['db_reset' => false]), true, false);
        $this->assertStringNotContainsString('name="process" value="setup"', $html);
        $this->assertStringContainsString('Disabled', $html);
    }

    public function testShowsSetupFormWhenResetEnabled(): void
    {
        $html = view_admin_html($this->settings(['db_reset' => true]), true, false);
        $this->assertStringContainsString('name="process" value="setup"', $html);
        $this->assertStringContainsString('to false to disable resets', $html);
    }

    public function testShowsCleanAndOptimizeFormsOnlyWhenInstalled(): void
    {
        $installed = view_admin_html($this->settings(), true, false);
        $this->assertStringContainsString('name="process" value="clean"', $installed);
        $this->assertStringContainsString('name="process" value="optimize"', $installed);

        $notInstalled = view_admin_html($this->settings(), false, false);
        $this->assertStringNotContainsString('name="process" value="clean"', $notInstalled);
        $this->assertStringNotContainsString('name="process" value="optimize"', $notInstalled);
    }

    public function testEscapesActionMessage(): void
    {
        // The setup/clean/optimize controllers return user-visible strings
        // that get rendered inside an <h3>; HTML in that string must be
        // escaped to keep this surface XSS-free.
        $html = view_admin_html(
            $this->settings(),
            true,
            false,
            '<script>alert(1)</script>',
        );
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testReportsCurrentPhpVersion(): void
    {
        $html = view_admin_html($this->settings(), true, false);
        $this->assertStringContainsString('PHP Version: '.PHP_VERSION, $html);
        // Composer enforces ^8.2; anything reaching this view is supported.
        $this->assertStringContainsString('Your PHP version is supported.', $html);
    }

    public function testFlagsUnsupportedPhpVersion(): void
    {
        // Manual installs may bypass composer's php: ^8.2 constraint, so the
        // view must still warn when the runtime is too old. The override
        // parameter exists purely so this branch can be reached from tests.
        $html = view_admin_html($this->settings(), true, false, false, false, '8.1.99');
        $this->assertStringContainsString('Phoenix requires PHP &gt;= 8.2.', $html);
        $this->assertStringContainsString('PHP Version: 8.1.99', $html);
        $this->assertStringNotContainsString('Your PHP version is supported.', $html);
    }

    public function testFlagsMissingMysqliExtension(): void
    {
        // Manual installs may bypass composer's ext-mysqli requirement, so the
        // view must still warn when mysqli is not loaded. When mysqli is
        // missing the panel short-circuits — no version line, no utilities.
        $html = view_admin_html($this->settings(), true, false, false, false, null, false);
        $this->assertStringContainsString('Your server does not support MySQL.', $html);
        $this->assertStringNotContainsString('Your server supports MySQL.', $html);
        $this->assertStringNotContainsString('name="process" value="setup"', $html);
        $this->assertStringNotContainsString('name="process" value="clean"', $html);
        $this->assertStringNotContainsString('name="process" value="optimize"', $html);
    }

}
