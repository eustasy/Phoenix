<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminPeersHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.peers.php';
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return ['phoenix_version' => 'Phoenix Test v.0',
            'phoenix_release' => 'Testing', 'admin_password' => 'hash', 'stats_geo' => false];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function peer(array $overrides = []): array
    {
        return array_merge([
            'info_hash' => str_repeat('a', 40),
            'peer_id' => str_repeat('1', 40),
            'ipv4' => '81.78.207.83',
            'ipv6' => '',
            'portv4' => 51413,
            'portv6' => 0,
            'uploaded' => 1474560,
            'downloaded' => 199229440,
            'left' => 0,
            'state' => 1,
            'updated' => 1700000000,
            'name' => 'Ubuntu 24.04.1 LTS',
            'filename' => 'ubuntu-24.04.1-desktop-amd64.iso',
            'client' => 'Transmission 4.1.1.0',
        ], $overrides);
    }

    public function testAddressLinksToThatPeersOtherSwarms(): void
    {
        // A peer holds one row per torrent, so filtering on its address lists
        // its torrents — and the window count reports how many. No separate
        // view needed for "torrents for this peer".
        $html = view_admin_peers_html($this->settings(), [$this->peer()], 1, 1, 0, 200, 'tok');

        $this->assertStringContainsString('q=81.78.207.83', $html);
        // The copy button keeps the full address including the port.
        $this->assertStringContainsString('data-copy="81.78.207.83:51413"', $html);
    }

    public function testSwarmFilterNamesTheTorrentAndDropsTheTorrentColumn(): void
    {
        // The per-torrent drill-down is this view with an info_hash filter, so a
        // busy swarm pages like any other listing. The Torrent column would
        // repeat the same name on every row, so it goes.
        $html = view_admin_peers_html(
            $this->settings(),
            [$this->peer()],
            1,
            1,
            0,
            200,
            'tok',
            '',
            -1,
            'updated',
            'desc',
            str_repeat('a', 40),
            'My Torrent',
        );

        $this->assertStringContainsString('Peers for <b>My Torrent</b>', $html);
        $this->assertStringContainsString('Show all peers', $html);
        $this->assertStringNotContainsString('>Torrent<', $html);
    }

    public function testSwarmFilterFallsBackToATruncatedHashWhenUnregistered(): void
    {
        $html = view_admin_peers_html(
            $this->settings(),
            [$this->peer()],
            1,
            1,
            0,
            200,
            'tok',
            '',
            -1,
            'updated',
            'desc',
            str_repeat('b', 40),
            null,
        );

        $this->assertStringContainsString('bbbbbbbbbbbb&hellip;', $html);
    }

    public function testSwarmFilterSurvivesSearchSortAndPaging(): void
    {
        // Every link and the form must carry info_hash, or searching or paging
        // inside a swarm would silently escape it.
        $html = view_admin_peers_html(
            $this->settings(),
            [$this->peer()],
            500,
            1,
            0,
            200,
            'tok',
            'x',
            -1,
            'uploaded',
            'asc',
            str_repeat('a', 40),
            'My Torrent',
        );

        $this->assertStringContainsString('name="info_hash" value="'.str_repeat('a', 40).'"', $html);
        // Every sort link and the pager carry it; the exact count is incidental.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, 'info_hash='.str_repeat('a', 40)),
            'the sort links and the pager should carry the swarm',
        );
    }

    public function testSearchIsAGetFormNotAClientSideFilter(): void
    {
        // The table is paged, so a browser-side filter could only ever search
        // the rendered page — search has to reach the server.
        $html = view_admin_peers_html($this->settings(), [$this->peer()], 1, 1, 0, 200, 'tok');

        $this->assertStringContainsString('<form method="GET"', $html);
        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringNotContainsString('data-filter-table', $html);
        $this->assertStringNotContainsString('/assets/tables.js', $html);
    }

    public function testQueryStateSurvivesSortLinksAndPager(): void
    {
        // Two pages' worth, so the pager renders.
        $html = view_admin_peers_html(
            $this->settings(),
            [$this->peer()],
            500,
            1,
            0,
            200,
            'tok',
            '81.78',
            1,
            'uploaded',
            'asc',
        );

        // Search and state are echoed back into the form...
        $this->assertStringContainsString('value="81.78"', $html);
        $this->assertStringContainsString('value="1" selected', $html);
        // ...and carried by both the sort links and the pager, or paging would
        // silently drop the filter.
        $this->assertStringContainsString('q=81.78', $html);
        $this->assertStringContainsString('offset=200', $html);
        $this->assertStringContainsString('sort=uploaded', $html);
    }

    public function testClearAppearsOnlyWhenAFilterIsSet(): void
    {
        $plain = view_admin_peers_html($this->settings(), [$this->peer()], 1, 1, 0, 200, 'tok');
        $filtered = view_admin_peers_html($this->settings(), [$this->peer()], 1, 1, 0, 200, 'tok', 'abc');

        $this->assertStringNotContainsString('>Clear<', $plain);
        $this->assertStringContainsString('>Clear<', $filtered);
    }

    public function testCountryColumnHiddenWhenGeoOff(): void
    {
        $html = view_admin_peers_html($this->settings(), [$this->peer()], 1, 1, 0, 200, 'tok');
        $this->assertStringNotContainsString('<th>Country</th>', $html);
        $this->assertStringNotContainsString('ph-cc', $html);
    }

    public function testCountryColumnShowsCodeWithNameOnHover(): void
    {
        $settings = $this->settings();
        $settings['stats_geo'] = true;
        $peer = $this->peer(['country' => 'GB', 'country_name' => 'United Kingdom']);

        $html = view_admin_peers_html($settings, [$peer], 1, 1, 0, 200, 'tok');
        $this->assertStringContainsString('<th>Country</th>', $html);
        $this->assertStringContainsString('<abbr class="ph-cc" title="United Kingdom">GB</abbr>', $html);
    }

    public function testCountryColumnDashesAnUnresolvedAddress(): void
    {
        // Geo on but this address is not in the database — the column still
        // renders, so the row keeps its column count.
        $settings = $this->settings();
        $settings['stats_geo'] = true;

        $html = view_admin_peers_html($settings, [$this->peer(['country' => '', 'country_name' => ''])], 1, 1, 0, 200, 'tok');
        $this->assertStringContainsString('<th>Country</th>', $html);
        $this->assertStringNotContainsString('ph-cc', $html);
    }

    public function testRendersBaseDocument(): void
    {
        $html = view_admin_peers_html($this->settings(), [], 0, 0, 0, 200, 'tok');
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Peers</title>', $html);
        $this->assertStringContainsString('<a href="?page=peers" class="is-active" aria-current="page">', $html);
        $this->assertStringContainsString('id="tbl-peers"', $html);
    }

    public function testRendersPeerRow(): void
    {
        $html = view_admin_peers_html($this->settings(), [$this->peer()], 1, 1, 0, 200, 'tok');
        $this->assertStringContainsString('Transmission 4.1.1.0', $html);
        $this->assertStringContainsString('Ubuntu 24.04.1 LTS', $html);
        $this->assertStringContainsString('81.78.207.83:51413', $html);
        $this->assertStringContainsString('Seeding', $html);
    }

    public function testLeechingAndIpv6Address(): void
    {
        $html = view_admin_peers_html(
            $this->settings(),
            [$this->peer(['state' => 0, 'ipv4' => '', 'ipv6' => '2001:db8::1', 'portv6' => 6881])],
            1,
            1,
            0,
            200,
            'tok',
        );
        $this->assertStringContainsString('Leeching', $html);
        $this->assertStringContainsString('[2001:db8::1]:6881', $html);
    }

    public function testUnregisteredSwarmShowsTruncatedHash(): void
    {
        $html = view_admin_peers_html(
            $this->settings(),
            [$this->peer(['info_hash' => str_repeat('c', 40), 'name' => null])],
            1,
            1,
            0,
            200,
            'tok',
        );
        $this->assertStringContainsString(str_repeat('c', 12).'&hellip;', $html);
    }

    public function testEscapesNameAndClient(): void
    {
        $html = view_admin_peers_html(
            $this->settings(),
            [$this->peer(['name' => '<b>x</b>', 'client' => '<script>y</script>'])],
            1,
            1,
            0,
            200,
            'tok',
        );
        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringNotContainsString('<script>y</script>', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testReportsTotalsAndWindow(): void
    {
        $html = view_admin_peers_html($this->settings(), [$this->peer()], 2841, 12, 0, 200, 'tok');
        $this->assertStringContainsString('<b>2,841</b> active peers', $html);
        $this->assertStringContainsString('12 swarms', $html);
        $this->assertStringContainsString('Showing 1&ndash;1 of 2,841', $html);
    }

    public function testEmptyShowsState(): void
    {
        $html = view_admin_peers_html($this->settings(), [], 0, 0, 0, 200, 'tok');
        $this->assertStringContainsString('No active peers.', $html);
        $this->assertStringContainsString('Showing 0 of 0', $html);
    }

    public function testPagerAppearsWhenMoreThanOnePage(): void
    {
        // total 500 > limit 200, on the first page: Next links to offset 200.
        $html = view_admin_peers_html($this->settings(), [$this->peer()], 500, 5, 0, 200, 'tok');
        $this->assertStringContainsString('?page=peers&amp;offset=200', $html);
        $this->assertStringContainsString('Next', $html);
        // Previous is disabled on the first page (no offset link below 0).
        $this->assertStringNotContainsString('offset=-', $html);
    }

    public function testNoPagerOnSinglePage(): void
    {
        $html = view_admin_peers_html($this->settings(), [$this->peer()], 1, 1, 0, 200, 'tok');
        $this->assertStringNotContainsString('?page=peers&amp;offset=', $html);
    }

    public function testTorrentCellCarriesHashAndFilenameOnHover(): void
    {
        // The column shows the display name, which is not an identifier: the
        // same release rebuilt carries it under a different hash.
        $html = view_admin_peers_html($this->settings(), [$this->peer()], 1, 1, 0, 200, 'tok');

        $this->assertStringContainsString(
            'title="'.str_repeat('a', 40)."\n".'ubuntu-24.04.1-desktop-amd64.iso"',
            $html,
        );
        $this->assertStringContainsString('ph-plain', $html);
    }
}
