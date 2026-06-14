<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminAddHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.add.php';
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
        $html = view_admin_add_html($this->settings(), true, false, '');
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Add a Torrent</title>', $html);
        $this->assertStringContainsString('<a href="?page=add" class="nav-link current" aria-current="page">Add Torrent</a>', $html);
    }

    public function testRendersFormWhenInstalled(): void
    {
        // The add-torrent form needs multipart encoding for its .torrent upload
        // and posts back to ?page=add.
        $html = view_admin_add_html($this->settings(), true, false, '');
        $this->assertStringContainsString('name="process" value="torrent_add"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('type="file" name="torrent"', $html);
        $this->assertStringContainsString('action="?page=add"', $html);
        // The file input sits in a drag-and-drop zone and accepts .torrent.
        $this->assertStringContainsString('id="torrent-drop"', $html);
        $this->assertStringContainsString('accept=".torrent', $html);
    }

    public function testShowsNoticeWhenTablesMissing(): void
    {
        // Without installed tables there is nothing to insert into, so the form
        // is replaced by a pointer to Utilities.
        $html = view_admin_add_html($this->settings(), false, false, '');
        $this->assertStringNotContainsString('name="process" value="torrent_add"', $html);
        $this->assertStringContainsString('not installed yet', $html);
        $this->assertStringContainsString('?page=utilities', $html);
    }

    public function testEmbedsCsrfTokenInForm(): void
    {
        // The add form (1) + the layout's logout form (needs admin_password) =
        // 2 occurrences of the token.
        $html = view_admin_add_html($this->settings(['admin_password' => 'hash']), true, false, 'deadbeefToken');
        $this->assertSame(2, substr_count($html, 'name="csrf" value="deadbeefToken"'));
    }

    public function testEscapesActionMessage(): void
    {
        $html = view_admin_add_html($this->settings(), true, '<script>alert(1)</script>', '');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
