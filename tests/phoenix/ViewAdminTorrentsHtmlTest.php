<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminTorrentsHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.torrents.php';
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
            'info_hash' => str_repeat('a', 40),
            'user' => 'alice',
            'name' => 'Test Torrent',
            'size' => 1024,
            'listed' => 1,
            'downloads' => 5,
            'seeders' => 3,
            'leechers' => 2,
            'peers' => 5,
            'traffic' => 123456,
            'filename' => null,
            'files' => null,
            'trackers' => null,
            'webseeds' => null,
        ], $overrides);
    }

    public function testRendersTableWithTorrentRow(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok');

        $this->assertStringContainsString('<table class="data-table">', $html);
        $this->assertStringContainsString('<th>Name</th>', $html);
        $this->assertStringContainsString('Test Torrent', $html);
        $this->assertStringContainsString('<code>'.str_repeat('a', 40).'</code>', $html);
        $this->assertStringContainsString('alice', $html);
        $this->assertStringContainsString('123,456', $html);
    }

    public function testListedTorrentOffersUnlist(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent(['listed' => 1])], false, 'tok');
        $this->assertStringContainsString('name="process" value="torrent_listed"', $html);
        // Listed → the toggle targets 0 and reads "Unlist".
        $this->assertStringContainsString('name="listed" value="0"', $html);
        $this->assertStringContainsString('>Unlist</button>', $html);
    }

    public function testUnlistedTorrentOffersList(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent(['listed' => 0])], false, 'tok');
        $this->assertStringContainsString('name="listed" value="1"', $html);
        $this->assertStringContainsString('>List</button>', $html);
    }

    public function testEachRowCarriesDeleteFormWithCsrfAndInfoHash(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok');
        $this->assertStringContainsString('name="process" value="torrent_delete"', $html);
        $this->assertStringContainsString('name="info_hash" value="'.str_repeat('a', 40).'"', $html);
        $this->assertStringContainsString('name="csrf" value="tok"', $html);
        $this->assertStringContainsString('>Delete</button>', $html);
    }

    public function testEscapesTorrentName(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent(['name' => '<script>alert(1)</script>'])], false, 'tok');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testNullOwnerRendersDash(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent(['user' => null])], false, 'tok');
        $this->assertStringContainsString('&mdash;', $html);
    }

    public function testEmptyListShowsMessage(): void
    {
        $html = view_admin_torrents_html($this->settings(), [], false, 'tok');
        $this->assertStringContainsString('No torrents are registered.', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function testActionMessageRenderedAndEscaped(): void
    {
        $html = view_admin_torrents_html($this->settings(), [], '<b>hi</b>', 'tok');
        $this->assertStringContainsString('&lt;b&gt;hi&lt;/b&gt;', $html);
    }

    public function testUsesWideLayoutAndMarksTorrentsNavActive(): void
    {
        $html = view_admin_torrents_html($this->settings(), [], false, 'tok');
        $this->assertStringContainsString('<body class="wide">', $html);
        $this->assertStringContainsString('href="?page=torrents" class="nav-link current" aria-current="page"', $html);
    }

    public function testEachRowLinksToPeerDrillDown(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok');
        $this->assertStringContainsString('href="?page=peers&amp;info_hash='.str_repeat('a', 40).'"', $html);
        $this->assertStringContainsString('>Peers</a>', $html);
    }

    public function testRendersUnregisteredSwarms(): void
    {
        // Swarms with peers but no torrents row are counted and shown, each
        // linking to its drill-down.
        $swarms = [['info_hash' => str_repeat('e', 40), 'seeders' => 5, 'leechers' => 1, 'peers' => 6]];
        $html = view_admin_torrents_html($this->settings(), [], false, 'tok', $swarms);

        $this->assertStringContainsString('Unregistered swarms', $html);
        $this->assertStringContainsString('<code>'.str_repeat('e', 40).'</code>', $html);
        $this->assertStringContainsString('href="?page=peers&amp;info_hash='.str_repeat('e', 40).'"', $html);
    }

    public function testNoUnregisteredSectionWhenNoSwarms(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok', []);
        $this->assertStringNotContainsString('Unregistered swarms', $html);
    }
}
