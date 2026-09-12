<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/cdn.assets.php';
        require_once __DIR__.'/../../src/views/html.admin.php';
    }

    /** @return array<string, mixed> */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'phoenix_version' => 'Phoenix Test v.0',
            'admin_password' => '',
        ], $overrides);
    }

    /** @return array<string, int> */
    private function stats(): array
    {
        return [
            'seeders' => 3, 'leechers' => 2, 'peers' => 5,
            'torrents' => 4, 'downloads' => 10, 'bandwidth' => 123456, 'registered' => 7,
        ];
    }

    /**
     * @return list<array{info_hash: string, name: string|null, filename: string|null, seeders: int, leechers: int, downloads: int, bandwidth: int}>
     */
    private function topTorrents(): array
    {
        return [
            ['info_hash' => str_repeat('a', 40), 'name' => 'Alpha', 'filename' => 'alpha.iso', 'seeders' => 50, 'leechers' => 1, 'downloads' => 10, 'bandwidth' => 0],
            ['info_hash' => str_repeat('b', 40), 'name' => null, 'filename' => null, 'seeders' => 20, 'leechers' => 0, 'downloads' => 10, 'bandwidth' => 0],
        ];
    }

    public function testBandwidthChartFillsTheOtherHalfOfTheChartRow(): void
    {
        $bandwidth = [
            ['time' => 1788739200, 'completions' => 76, 'bytes' => 250263275520],
            ['time' => 1788825600, 'completions' => 79, 'bytes' => 260362334208],
        ];
        $html = view_admin_html(
            $this->settings(),
            true,
            false,
            'tok',
            false,
            [],
            [],
            [],
            [],
            ['Transmission' => ['4.1.3.0' => 49]],
            $bandwidth,
        );

        // Both charts, and the library pulled once for the pair. Asserted
        // against the pinned table rather than a literal version, so a bump is
        // one edit in cdn_assets() and not a test failure.
        $this->assertStringContainsString('<canvas id="clients-chart">', $html);
        $this->assertStringContainsString('<canvas id="bandwidth-chart">', $html);
        $this->assertSame(1, substr_count($html, \cdn_assets()['chart']['url']));
        // …and it carries its integrity hash.
        $this->assertStringContainsString(
            'integrity="'.\cdn_assets()['chart']['integrity'].'" crossorigin="anonymous"',
            $html,
        );
        // The card footers through to the full page.
        $this->assertStringContainsString('476 GB', $html);
        $this->assertStringContainsString('?page=bandwidth', $html);
    }

    public function testClientChartLeadsTheSectionAtHalfWidth(): void
    {
        $clients = ['Transmission' => ['4.1.3.0' => 49, '3.0.0.0' => 33], 'qBittorrent' => ['5.2.3.0' => 52]];
        $html = view_admin_html(
            $this->settings(),
            true,
            false,
            'tok',
            false,
            [],
            [],
            ['countries' => ['GB' => 5]],
            [],
            $clients,
        );

        $this->assertStringContainsString('<canvas id="clients-chart">', $html);
        // Two-column grid, and ahead of the ranked cards.
        $this->assertLessThan(
            strpos($html, 'ph-toplist-grid'),
            strpos($html, 'ph-chart-grid'),
            'charts lead the section',
        );
        // Data is inlined for the chart script, like the geography map.
        $this->assertStringContainsString('var CLIENTS =', $html);
        $this->assertStringContainsString('"Transmission"', $html);
    }

    public function testClientChartAbsentWhenThereAreNoPeers(): void
    {
        // No chart, and no reason to pull the library in either.
        $html = view_admin_html($this->settings(), true, false, 'tok', false, [], [], [], [], []);

        $this->assertStringNotContainsString('clients-chart', $html);
        $this->assertStringNotContainsString('chart.js', $html);
    }

    public function testCountMapCardsAreRankedNotJustSliced(): void
    {
        // peers_geo_counts() returns whatever order it resolved addresses in,
        // so slicing without sorting showed five arbitrary countries.
        $html = view_admin_html(
            $this->settings(),
            true,
            false,
            'tok',
            false,
            [],
            [],
            ['countries' => ['AF' => 3, 'GB' => 120, 'US' => 75, 'BR' => 90, 'DE' => 12, 'FR' => 1]],
        );

        preg_match_all('#<span class="nm">([A-Z]{2})</span>#', $html, $m);
        $this->assertSame(['GB', 'BR', 'US', 'DE', 'AF'], $m[1]);
    }

    public function testCountMapRowsDoNotEachLinkToTheSamePage(): void
    {
        // Every row pointing at the same destination is noise; the card's
        // footer link covers it once.
        $html = view_admin_html(
            $this->settings(),
            true,
            false,
            'tok',
            false,
            [],
            [],
            ['countries' => ['GB' => 5]],
        );

        $this->assertStringNotContainsString('<a class="nm"', $html);
        $this->assertStringContainsString('ph-toplist-more', $html);
    }

    public function testTopSeedersAndLeechersRankPeersNotTorrents(): void
    {
        $peers = [[
            'address' => '81.78.207.83', 'peer_id' => 'x', 'info_hash' => str_repeat('a', 40),
            'name' => 'A', 'bytes' => 1610612736,
        ]];
        $html = view_admin_html(
            $this->settings(),
            true,
            false,
            'tok',
            false,
            [],
            [],
            [],
            ['seeders' => $peers, 'leechers' => $peers],
        );

        $this->assertStringContainsString('>Top seeders<', $html);
        $this->assertStringContainsString('>Top leechers<', $html);
        // A row links to every swarm that peer is in.
        $this->assertStringContainsString('?page=peers&amp;q=81.78.207.83', $html);
        $this->assertStringContainsString('1.5 GB', $html);
    }

    public function testMiniTableCardsLinkIntoTheListingTheySummarise(): void
    {
        $html = view_admin_html(
            $this->settings(),
            true,
            false,
            'tok',
            false,
            [],
            ['seeded' => $this->topTorrents()],
            ['countries' => ['GB' => 52]],
        );

        $this->assertStringContainsString('At a glance', $html);
        $this->assertStringContainsString('>Most seeded<', $html);
        $this->assertStringContainsString('>Top countries<', $html);
        // A card is a way in, not a dead end.
        $this->assertStringContainsString('?page=peers&amp;info_hash='.str_repeat('a', 40), $html);
        // An unregistered swarm falls back to a truncated hash.
        $this->assertStringContainsString('bbbbbbbbbbbb', $html);
    }

    public function testEmptyCardsAreDroppedRatherThanShownEmpty(): void
    {
        // A tracker with no unhealthy swarms should not be told so every visit.
        $html = view_admin_html($this->settings(), true, false, 'tok', false, [], ['trouble' => []], []);

        $this->assertStringNotContainsString('Torrents in trouble', $html);
        $this->assertStringNotContainsString('ph-toplist-grid', $html);
    }

    public function testRendersBaseDocument(): void
    {
        $html = view_admin_html($this->settings(), true);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Dashboard</title>', $html);
        $this->assertStringContainsString('Phoenix Test v.0', $html);
    }

    public function testShowsLogoutFormWhenAdminPasswordSet(): void
    {
        // Logout form only appears when auth is configured; otherwise there's
        // nothing to log out of and rendering the form would be confusing.
        $withPassword = view_admin_html($this->settings(['admin_password' => 'hash']), true, false, 'tok');
        $this->assertStringContainsString('name="logout" value="1"', $withPassword);
        $this->assertStringContainsString('Log out', $withPassword);

        $noPassword = view_admin_html($this->settings(), true);
        $this->assertStringNotContainsString('name="logout"', $noPassword);
    }

    public function testShowsInstalledBannerWhenFlagged(): void
    {
        // admin.php sets show_installed=true after redirecting from the
        // installer so the user sees confirmation on first load.
        $html = view_admin_html($this->settings(), true, true);
        $this->assertStringContainsString('Installation complete.', $html);
    }

    public function testRendersStatsBlockWhenStatsProvided(): void
    {
        // When the dashboard controller supplies stats, the overview renders
        // with number_format()-ed figures and last-run lines.
        $tasks = [
            'clean' => ['value' => 1700000000, 'source' => 'cron'],
            'optimize' => ['value' => 1700000100, 'source' => 'admin'],
        ];
        $html = view_admin_html($this->settings(), true, false, '', $this->stats(), $tasks);

        // Stat cards carry the headline figures.
        $this->assertStringContainsString('Active peers', $html);
        $this->assertStringContainsString('<div class="ph-stat-value">5</div>', $html);
        $this->assertStringContainsString('Registered torrents', $html);
        $this->assertStringContainsString('<div class="ph-stat-value">7</div>', $html);
        $this->assertStringContainsString('with active peers', $html);
        $this->assertStringContainsString('Bandwidth served', $html);
        $this->assertStringContainsString('123,456 bytes', $html);
        // Maintenance rows render only for tasks that have run, with a By column
        // naming who ran each (capitalised source).
        $this->assertStringContainsString('Pruned', $html);
        $this->assertStringContainsString('Optimized', $html);
        $this->assertStringNotContainsString('Migrated', $html);
        $this->assertStringContainsString('<th>By</th>', $html);
        // Scheduled runs stay plain; a manual one is coloured, matching the
        // Task History page.
        $this->assertStringContainsString('<span class="badge">Cron</span>', $html);
        $this->assertStringContainsString('<span class="badge badge-blue">Admin</span>', $html);
    }

    public function testMaintenanceLinksToTheFullHistory(): void
    {
        $tasks = ['clean' => ['value' => 1700000000, 'source' => 'cron']];

        $html = view_admin_html($this->settings(), true, false, '', $this->stats(), $tasks);

        // The block shows only the last run of each task; the log is elsewhere.
        $this->assertStringContainsString('?page=tasks', $html);
    }

    public function testShowsNotInstalledNoticeWhenTablesMissing(): void
    {
        // No stats and no tables → point the operator at Utilities/Support.
        $html = view_admin_html($this->settings(), false);
        $this->assertStringContainsString('database is not installed yet', $html);
        $this->assertStringContainsString('?page=utilities', $html);
        $this->assertStringContainsString('?page=support', $html);
    }

    public function testShowsNoStatsNoticeWhenInstalledButNoStats(): void
    {
        // Tables present but no aggregated stats yet → a neutral notice rather
        // than the not-installed warning.
        $html = view_admin_html($this->settings(), true);
        $this->assertStringContainsString('No tracker statistics yet.', $html);
    }

    public function testOmitsMaintenanceFormsAndDiagnostics(): void
    {
        // Setup/clean/optimize/migrate, the add-torrent form, and the
        // compatibility diagnostics all moved to their own pages; the
        // dashboard is now a read-only overview.
        $html = view_admin_html($this->settings(), true, false, '', $this->stats(), []);
        $this->assertStringNotContainsString('name="process" value="setup"', $html);
        $this->assertStringNotContainsString('name="process" value="clean"', $html);
        $this->assertStringNotContainsString('name="process" value="torrent_add"', $html);
        $this->assertStringNotContainsString('Compatibility Check', $html);
        $this->assertStringNotContainsString('Your server supports MySQL.', $html);
    }

    public function testTorrentCardRowsCarryHashAndFilenameOnHover(): void
    {
        // A card row shows one line; the identifying detail goes in the tooltip.
        $html = view_admin_html(
            $this->settings(),
            true,
            false,
            'tok',
            $this->stats(),
            [],
            ['seeded' => $this->topTorrents(), 'leeched' => $this->topTorrents()],
        );

        $this->assertStringContainsString('title="'.str_repeat('a', 40)."\n".'alpha.iso"', $html);
        // A torrent with no filename still offers the hash.
        $this->assertStringContainsString('title="'.str_repeat('b', 40).'"', $html);
    }

    public function testCardHeadingsSurviveRowTooltips(): void
    {
        // The row tooltip and the card's own heading must not share a
        // variable — holding both in $title renders the heading as the tooltip.
        $html = view_admin_html(
            $this->settings(),
            true,
            false,
            'tok',
            $this->stats(),
            [],
            ['seeded' => $this->topTorrents(), 'leeched' => $this->topTorrents()],
        );

        $this->assertStringContainsString('<h3>Most seeded</h3>', $html);
        $this->assertStringContainsString('<h3>Most leeched</h3>', $html);
    }

    public function testBandwidthCardRowsLinkToTheBandwidthDrillDown(): void
    {
        // A row links into the view that answers the question its card asked:
        // the swarm cards to Peers, the bytes card to Bandwidth.
        $html = view_admin_html(
            $this->settings(),
            true,
            false,
            'tok',
            $this->stats(),
            [],
            ['seeded' => $this->topTorrents(), 'bandwidth' => $this->topTorrents()],
        );

        $this->assertStringContainsString('?page=bandwidth&amp;info_hash='.str_repeat('a', 40), $html);
        $this->assertStringContainsString('?page=peers&amp;info_hash='.str_repeat('a', 40), $html);
    }

    public function testOverdueTaskHighlightsItsTimestamp(): void
    {
        $tasks = [
            'clean' => ['value' => 1700000000, 'source' => 'cron', 'state' => 'overdue', 'age' => 7200, 'after' => 3600],
        ];

        $html = view_admin_html($this->settings(), true, false, '', $this->stats(), $tasks);

        // The timestamp carries the alert, so the thing that is wrong and the
        // reading that says so are the same word.
        $this->assertStringContainsString('ph-task-alert is-warning', $html);
        $this->assertStringContainsString('data-lucide="triangle-alert"', $html);
        $this->assertStringContainsString('badge-yellow">overdue', $html);
        // The hover says how late and how often, so the threshold reads as a
        // period rather than as another point in the past.
        $this->assertStringContainsString('Last run 2h ago, expected every 1h', $html);
    }

    public function testNeverRunTaskGetsItsOwnRowAndHighlight(): void
    {
        // Not late, but never started — a different problem with a different
        // fix, so it reads differently.
        $tasks = [
            'backup' => ['value' => 0, 'source' => '', 'state' => 'never', 'age' => null, 'after' => 172800],
        ];

        $html = view_admin_html($this->settings(), true, false, '', $this->stats(), $tasks);

        $this->assertStringContainsString('Backed up', $html);
        $this->assertStringContainsString('ph-task-alert is-critical', $html);
        $this->assertStringContainsString('data-lucide="circle-alert"', $html);
        $this->assertStringContainsString('never run', $html);
        $this->assertStringNotContainsString('1970-01-01', $html);
    }

    public function testHealthyTaskIsNotHighlighted(): void
    {
        $tasks = [
            'clean' => ['value' => 1700000000, 'source' => 'cron', 'state' => 'ok', 'age' => 60, 'after' => 3600],
        ];

        $html = view_admin_html($this->settings(), true, false, '', $this->stats(), $tasks);

        $this->assertStringContainsString('badge-green">done', $html);
        $this->assertStringNotContainsString('ph-task-alert', $html);
    }
}
