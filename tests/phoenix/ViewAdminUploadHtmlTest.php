<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminUploadHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.upload.php';
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return ['phoenix_version' => 'Phoenix Test v.0', 'admin_password' => 'hash'];
    }

    public function testRendersUploaderWhenInstalledAndTokenPresent(): void
    {
        $html = view_admin_upload_html($this->settings(), true, 'deadbeef');
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Bulk Upload</title>', $html);
        // It's a sibling of single-add, so the Add nav stays current.
        $this->assertStringContainsString('<a href="?page=add" class="is-active" aria-current="page">', $html);
        // The uploader, its CSRF token, the file/folder pickers, and the script
        // that drives them (the API endpoint lives in assets/upload.js).
        $this->assertStringContainsString('id="bulk-drop"', $html);
        $this->assertStringContainsString('data-csrf="deadbeef"', $html);
        $this->assertStringContainsString('webkitdirectory', $html);
        $this->assertStringContainsString('multiple', $html);
        $this->assertStringContainsString('/assets/upload.js', $html);
    }

    public function testNoticeWhenTablesMissing(): void
    {
        $html = view_admin_upload_html($this->settings(), false, 'deadbeef');
        $this->assertStringContainsString('not installed yet', $html);
        $this->assertStringContainsString('?page=utilities', $html);
        $this->assertStringNotContainsString('id="bulk-drop"', $html);
    }

    public function testNoticeWhenNoAdminPassword(): void
    {
        // No token → the session-authed API can't be reached; explain instead
        // of rendering an uploader that would always fail.
        $html = view_admin_upload_html($this->settings(), true, '');
        $this->assertStringContainsString('admin password', $html);
        $this->assertStringNotContainsString('id="bulk-drop"', $html);
        $this->assertStringNotContainsString('/assets/upload.js', $html);
    }
}
