<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminSupportHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.support.php';
    }

    /** @return array<string, mixed> */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'phoenix_version' => 'Phoenix Test v.0',
            'admin_password' => '',
        ], $overrides);
    }

    public function testRendersBaseDocument(): void
    {
        $html = view_admin_support_html($this->settings(), true, false);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Server Support</title>', $html);
        // The Server Support nav link is marked current on this page.
        $this->assertStringContainsString('<a href="?page=support" class="is-active" aria-current="page">', $html);
    }

    public function testReportsCurrentPhpVersion(): void
    {
        $html = view_admin_support_html($this->settings(), true, false);
        $this->assertStringContainsString('PHP Version: '.PHP_VERSION, $html);
        // Composer enforces ^8.2; anything reaching this view is supported.
        $this->assertStringContainsString('Your PHP version is supported.', $html);
    }

    public function testFlagsUnsupportedPhpVersion(): void
    {
        // Manual installs may bypass composer's php: ^8.2 constraint, so the
        // view must still warn when the runtime is too old. The override
        // parameter exists purely so this branch can be reached from tests.
        $html = view_admin_support_html($this->settings(), true, false, '', '8.1.99');
        $this->assertStringContainsString('Phoenix requires PHP &gt;= 8.2.', $html);
        $this->assertStringContainsString('PHP Version: 8.1.99', $html);
        $this->assertStringNotContainsString('Your PHP version is supported.', $html);
    }

    public function testReportsMysqlSupportWhenAvailable(): void
    {
        // Default has_mysqli (class_exists) — mysqli is present in the test env.
        $html = view_admin_support_html($this->settings(), true, false);
        $this->assertStringContainsString('Your server supports MySQL.', $html);
    }

    public function testFlagsMissingMysqliExtension(): void
    {
        // Manual installs may bypass composer's ext-mysqli requirement, so the
        // view must still warn when mysqli is not loaded. The override exists
        // purely so this branch can be reached from tests.
        $html = view_admin_support_html($this->settings(), true, false, '', null, false);
        $this->assertStringContainsString('Your server does not support MySQL.', $html);
        $this->assertStringNotContainsString('Your server supports MySQL.', $html);
    }

    public function testShowsTablesInstalledWithSize(): void
    {
        $html = view_admin_support_html(
            $this->settings(),
            true,
            ['Data' => 100, 'Indexes' => 50, 'Total' => 1234567, 'Free' => 0],
        );
        $this->assertStringContainsString('All your tables are installed.', $html);
        $this->assertStringContainsString('1,234,567 bytes', $html);
    }

    public function testShowsTablesMissingWarningWhenNotInstalled(): void
    {
        $html = view_admin_support_html($this->settings(), false, false);
        $this->assertStringContainsString('Some or all of your tables are not installed.', $html);
        // Points the operator at Utilities to install them.
        $this->assertStringContainsString('?page=utilities', $html);
    }
}
