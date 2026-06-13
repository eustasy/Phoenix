<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminLayoutHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.layout.php';
    }

    /** @return array<string, mixed> */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'phoenix_version' => 'Phoenix Test v.0',
            'admin_password' => '',
        ], $overrides);
    }

    public function testRendersFullDocumentWithPageTitle(): void
    {
        $html = view_admin_layout_html($this->settings(), 'Torrents', '<p>body</p>', 'torrents');
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Torrents</title>', $html);
    }

    public function testInsertsBody(): void
    {
        // The body is trusted HTML assembled by the controller and must be
        // emitted verbatim, not escaped.
        $html = view_admin_layout_html($this->settings(), 'Dashboard', '<p>BODY-MARKER</p>', 'dashboard');
        $this->assertStringContainsString('<p>BODY-MARKER</p>', $html);
    }

    public function testRendersAllFourNavLinks(): void
    {
        $html = view_admin_layout_html($this->settings(), 'Dashboard', '', 'dashboard');
        $this->assertStringContainsString('?page=dashboard', $html);
        $this->assertStringContainsString('?page=torrents', $html);
        $this->assertStringContainsString('?page=backups', $html);
        $this->assertStringContainsString('?page=settings', $html);
    }

    public function testHighlightsActiveNavLink(): void
    {
        // The active page's link carries aria-current="page" so users and
        // assistive tech (and tests) can tell which page is showing.
        $html = view_admin_layout_html($this->settings(), 'Backups', '', 'backups');
        $this->assertStringContainsString('<a href="?page=backups" class="nav-link current" aria-current="page">Backups</a>', $html);
    }

    public function testDoesNotHighlightInactiveNavLink(): void
    {
        $html = view_admin_layout_html($this->settings(), 'Backups', '', 'backups');
        // A non-active link is a plain nav-link with no current marker.
        $this->assertStringContainsString('<a href="?page=dashboard" class="nav-link">Dashboard</a>', $html);
    }

    public function testShowsLogoutFormWithCsrfWhenAdminPasswordSet(): void
    {
        $html = view_admin_layout_html(
            $this->settings(['admin_password' => 'hash']),
            'Dashboard',
            '',
            'dashboard',
            'deadbeefToken',
        );
        $this->assertStringContainsString('name="logout" value="1"', $html);
        $this->assertStringContainsString('Log out', $html);
        $this->assertStringContainsString('name="csrf" value="deadbeefToken"', $html);
    }

    public function testHidesLogoutFormWhenNoAdminPassword(): void
    {
        // No auth configured means nothing to log out of; the form must not
        // render (nor leak a csrf field).
        $html = view_admin_layout_html($this->settings(), 'Dashboard', '', 'dashboard', 'deadbeefToken');
        $this->assertStringNotContainsString('name="logout"', $html);
    }

    public function testIncludesVersionLine(): void
    {
        $html = view_admin_layout_html($this->settings(), 'Dashboard', '', 'dashboard');
        $this->assertStringContainsString('Phoenix Test v.0', $html);
    }

}
