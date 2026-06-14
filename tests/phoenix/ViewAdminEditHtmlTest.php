<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminEditHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.edit.php';
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return ['phoenix_version' => 'Phoenix Test v.0', 'admin_password' => 'hash'];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function torrent(array $overrides = []): array
    {
        return array_merge([
            'info_hash' => str_repeat('c', 40),
            'user' => null,
            'name' => 'Test Torrent',
            'size' => 4096,
            'listed' => 1,
            'downloads' => 0,
            'filename' => 'file.iso',
            'files' => [['path' => 'a/b.iso', 'length' => 4096]],
            'trackers' => ['http://t1/announce', 'http://t2/announce'],
            'webseeds' => ['http://ws/'],
        ], $overrides);
    }

    public function testRendersPrefilledForm(): void
    {
        $html = view_admin_edit_html($this->settings(), str_repeat('c', 40), $this->torrent(), false, 'tok');

        $this->assertStringContainsString('<title>Phoenix Admin: Edit Torrent</title>', $html);
        $this->assertStringContainsString('name="process" value="torrent_edit"', $html);
        $this->assertStringContainsString('name="name" value="Test Torrent"', $html);
        $this->assertStringContainsString('name="size" value="4096"', $html);
        $this->assertStringContainsString('name="listed" value="1" checked', $html);
        $this->assertStringContainsString('name="filename" value="file.iso"', $html);
        // Meta round-trips into the request shape the form posts back.
        $this->assertStringContainsString('[{"path":"a/b.iso","length":4096}]', $html);
        $this->assertStringContainsString("http://t1/announce\nhttp://t2/announce", $html);
        $this->assertStringContainsString('name="csrf" value="tok"', $html);
        $this->assertStringContainsString('action="?page=edit&amp;info_hash='.str_repeat('c', 40).'"', $html);
    }

    public function testUnlistedTorrentBoxNotChecked(): void
    {
        $html = view_admin_edit_html($this->settings(), str_repeat('c', 40), $this->torrent(['listed' => 0]), false, 'tok');
        $this->assertStringNotContainsString('value="1" checked', $html);
    }

    public function testEmptyMetaRendersBlankFields(): void
    {
        $html = view_admin_edit_html(
            $this->settings(),
            str_repeat('c', 40),
            $this->torrent(['files' => null, 'trackers' => null, 'webseeds' => null, 'filename' => null, 'name' => null]),
            false,
            'tok',
        );
        $this->assertStringContainsString('<textarea name="files"></textarea>', $html);
        $this->assertStringContainsString('name="name" value=""', $html);
    }

    public function testEscapesName(): void
    {
        $html = view_admin_edit_html($this->settings(), str_repeat('c', 40), $this->torrent(['name' => '<script>alert(1)</script>']), false, 'tok');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testNotFoundShowsNoticeAndNoForm(): void
    {
        $html = view_admin_edit_html($this->settings(), str_repeat('d', 40), false, false, 'tok');
        $this->assertStringContainsString('Torrent not found.', $html);
        $this->assertStringNotContainsString('name="process" value="torrent_edit"', $html);
    }

    public function testMarksTorrentsNavActive(): void
    {
        $html = view_admin_edit_html($this->settings(), str_repeat('c', 40), $this->torrent(), false, 'tok');
        $this->assertStringContainsString('href="?page=torrents" class="nav-link current" aria-current="page"', $html);
    }
}
