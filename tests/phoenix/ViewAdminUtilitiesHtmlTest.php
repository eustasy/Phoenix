<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminUtilitiesHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.utilities.php';
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
        $html = view_admin_utilities_html($this->settings(), true, false, '');
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Utilities</title>', $html);
        $this->assertStringContainsString('<a href="?page=utilities" class="nav-link current" aria-current="page">Utilities</a>', $html);
    }

    public function testShowsSetupFormWhenResetEnabled(): void
    {
        $html = view_admin_utilities_html($this->settings(['db_reset' => true]), true, false, '');
        $this->assertStringContainsString('name="process" value="setup"', $html);
        $this->assertStringContainsString('to false to disable resets', $html);
    }

    public function testShowsSetupFormWhenTablesMissingRegardlessOfReset(): void
    {
        $html = view_admin_utilities_html($this->settings(['db_reset' => false]), false, false, '');
        $this->assertStringContainsString('name="process" value="setup"', $html);
    }

    public function testHidesSetupFormWhenInstalledAndResetDisabled(): void
    {
        // db_reset=false + tables_installed=true means setup is locked; the
        // admin sees a "Disabled" badge instead of an actionable button.
        $html = view_admin_utilities_html($this->settings(['db_reset' => false]), true, false, '');
        $this->assertStringNotContainsString('name="process" value="setup"', $html);
        $this->assertStringContainsString('Disabled', $html);
    }

    public function testShowsCleanOptimizeMigrateOnlyWhenInstalled(): void
    {
        $installed = view_admin_utilities_html($this->settings(), true, false, '');
        $this->assertStringContainsString('name="process" value="clean"', $installed);
        $this->assertStringContainsString('name="process" value="optimize"', $installed);
        $this->assertStringContainsString('name="process" value="migrate"', $installed);

        $notInstalled = view_admin_utilities_html($this->settings(), false, false, '');
        $this->assertStringNotContainsString('name="process" value="clean"', $notInstalled);
        $this->assertStringNotContainsString('name="process" value="optimize"', $notInstalled);
        $this->assertStringNotContainsString('name="process" value="migrate"', $notInstalled);
    }

    public function testEmbedsCsrfTokenInEveryForm(): void
    {
        // setup, clean, optimize, migrate (4 page forms) + the layout's logout
        // form (needs admin_password) = 5 occurrences of the token.
        $html = view_admin_utilities_html(
            $this->settings(['admin_password' => 'hash', 'db_reset' => true]),
            true,
            false,
            'deadbeefToken',
        );
        $this->assertSame(5, substr_count($html, 'name="csrf" value="deadbeefToken"'));
    }

    public function testEscapesActionMessage(): void
    {
        // Action controllers return user-visible strings rendered inside an
        // <h3>; HTML in that string must be escaped to keep this XSS-free.
        $html = view_admin_utilities_html($this->settings(), true, '<script>alert(1)</script>', '');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
