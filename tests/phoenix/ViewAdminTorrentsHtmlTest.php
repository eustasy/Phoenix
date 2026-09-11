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

        $this->assertStringContainsString('ph-card-table', $html);
        // Headers are sort links now, not client-side sort handles.
        $this->assertStringContainsString('ph-sort-link', $html);
        $this->assertStringContainsString('>Name', $html);
        $this->assertStringContainsString('Test Torrent', $html);
        // The hash cell carries the full info hash for click-to-copy.
        $this->assertStringContainsString('data-copy="'.str_repeat('a', 40).'"', $html);
        $this->assertStringContainsString('alice', $html);
        // Size renders human-readable (1024 bytes -> 1.0 KB).
        $this->assertStringContainsString('1.0 KB', $html);
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
        // With no torrents and no unregistered swarms, no data table renders.
        $this->assertStringNotContainsString('<table', $html);
    }

    public function testActionMessageRenderedAndEscaped(): void
    {
        $html = view_admin_torrents_html($this->settings(), [], '<b>hi</b>', 'tok');
        $this->assertStringContainsString('&lt;b&gt;hi&lt;/b&gt;', $html);
    }

    public function testMarksTorrentsNavActive(): void
    {
        $html = view_admin_torrents_html($this->settings(), [], false, 'tok');
        $this->assertStringContainsString('href="?page=torrents" class="is-active" aria-current="page"', $html);
    }

    public function testEachRowLinksToPeerDrillDown(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok');
        $this->assertStringContainsString('href="?page=peers&amp;info_hash='.str_repeat('a', 40).'"', $html);
        $this->assertStringContainsString('>Peers</a>', $html);
    }

    public function testEachRowLinksToEdit(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok');
        $this->assertStringContainsString('href="?page=edit&amp;info_hash='.str_repeat('a', 40).'"', $html);
        $this->assertStringContainsString('>Edit</a>', $html);
    }

    public function testRendersUnregisteredSwarms(): void
    {
        // Swarms with peers but no torrents row are counted and shown, each
        // linking to its drill-down.
        $swarms = [['info_hash' => str_repeat('e', 40), 'seeders' => 5, 'leechers' => 1, 'peers' => 6]];
        $html = view_admin_torrents_html($this->settings(), [], false, 'tok', $swarms);

        $this->assertStringContainsString('Unregistered swarms', $html);
        $this->assertStringContainsString('data-copy="'.str_repeat('e', 40).'"', $html);
        $this->assertStringContainsString('href="?page=peers&amp;info_hash='.str_repeat('e', 40).'"', $html);
    }

    public function testNoUnregisteredSectionWhenNoSwarms(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok', []);
        $this->assertStringNotContainsString('Unregistered swarms', $html);
    }

    public function testSearchAndFilterAreAGetForm(): void
    {
        // Server-side, because the listing is paged: a browser-side filter would
        // only ever search the rendered page.
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok', [], 1);

        $this->assertStringContainsString('<form method="GET"', $html);
        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('name="listed"', $html);
        $this->assertStringNotContainsString('data-filter-table', $html);
    }

    public function testFilterStateSurvivesInLinks(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok', [], 1, 0, 100, 'ubuntu', 1);

        // The search box keeps its term, and the sort headers carry it forward
        // rather than dropping back to the whole table.
        $this->assertStringContainsString('value="ubuntu"', $html);
        $this->assertStringContainsString('q=ubuntu', $html);
        $this->assertStringContainsString('listed=1', $html);
        $this->assertStringContainsString('>Clear</a>', $html);
    }

    public function testPagerAppearsOnlyWhenThereIsAnotherPage(): void
    {
        $one = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok', [], 1);
        $this->assertStringNotContainsString('>Next', $one);
        $this->assertStringContainsString('Showing 1&ndash;1 of 1', $one);

        $paged = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok', [], 250, 0, 1);
        $this->assertStringContainsString('offset=1', $paged);
        $this->assertStringContainsString('Showing 1&ndash;1 of 250', $paged);
    }

    public function testSecondPageOffersPrevious(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok', [], 250, 100, 100);

        $this->assertStringContainsString('Showing 101&ndash;101 of 250', $html);
        $this->assertStringContainsString('offset=0', $html);
    }

    public function testActiveSortColumnIsMarked(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok', [], 1, 0, 100, '', -1, 'size', 'asc');

        $this->assertStringContainsString('ph-sort-link is-on', $html);
        // Clicking the active column flips it back.
        $this->assertStringContainsString('dir=desc', $html);
    }

    public function testEmptyFilteredListOffersWayBack(): void
    {
        // An empty page under a filter is a different fact from an empty
        // tracker, and must not read as "no torrents are registered".
        $html = view_admin_torrents_html($this->settings(), [], false, 'tok', [], 0, 0, 100, 'nothing-matches');

        $this->assertStringContainsString('No torrents match this filter.', $html);
        $this->assertStringNotContainsString('No torrents are registered.', $html);
        $this->assertStringContainsString('href="?page=torrents"', $html);
    }

    public function testHeaderCountIsTheFilterTotalNotThePage(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok', [], 4096);
        $this->assertStringContainsString('<b>4,096</b> torrents', $html);
    }

    public function testEachRowLinksToItsTrafficView(): void
    {
        $html = view_admin_torrents_html($this->settings(), [$this->torrent()], false, 'tok', [], 1);

        $this->assertStringContainsString('href="?page=traffic&amp;info_hash='.str_repeat('a', 40).'"', $html);
        $this->assertStringContainsString('>Traffic</a>', $html);
    }
}
