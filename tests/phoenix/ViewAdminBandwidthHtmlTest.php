<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminBandwidthHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.bandwidth.php';
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return ['phoenix_version' => 'Phoenix Test v.0', 'admin_password' => 'hash'];
    }

    /** @return array<array-key, array{days: int, bucket: int, label: string}> */
    private function windows(): array
    {
        return ['90' => ['days' => 90, 'bucket' => 86400, 'label' => '90 days']];
    }

    /** @return list<array{info_hash: string, name: string|null, filename: string|null, user: string|null, size: int, downloads: int, estimated: int, uploaded: int, downloaded: int, peers: int}> */
    private function torrents(): array
    {
        return [[
            'info_hash' => str_repeat('a', 40), 'name' => 'Alpha', 'filename' => 'alpha.iso', 'user' => 'alice', 'size' => 3335405568,
            'downloads' => 28660, 'estimated' => 95689450340352,
            'uploaded' => 201102059384, 'downloaded' => 0, 'peers' => 121,
        ]];
    }

    /** @return list<array{label: string, client: string, torrent: string|null, uploaded: int, downloaded: int}> */
    private function swarm(): array
    {
        return [[
            'label' => '81.78.207.83', 'client' => 'Transmission 4.1.3.0',
            'torrent' => 'Alpha', 'uploaded' => 201102059384, 'downloaded' => 1048576,
        ]];
    }

    public function testAllTimeMetricDrawsTheLedgerSeries(): void
    {
        $series = [['time' => 1788739200, 'completions' => 76, 'bytes' => 250263275520]];
        $html = view_admin_bandwidth_html($this->settings(), $series, $this->torrents(), 'events', '90', $this->windows(), 'tok');

        $this->assertStringContainsString('canvas id="bandwidth-chart"', $html);
        $this->assertStringContainsString('var TRAFFIC =', $html);
        // Windows only make sense against a series.
        $this->assertStringContainsString('>90 days<', $html);
    }

    public function testLiveSwarmDrawsPeersNotAHistoryItCannotHave(): void
    {
        // The live counters are cumulative-since-client-start and vanish when a
        // peer leaves, so plotting them over time would claim something the
        // tracker cannot know.
        $html = view_admin_bandwidth_html($this->settings(), [], $this->torrents(), 'peers', '90', $this->windows(), 'tok', $this->swarm());

        $this->assertStringContainsString('canvas id="swarm-chart"', $html);
        $this->assertStringNotContainsString('bandwidth-chart', $html);
        $this->assertStringNotContainsString('>90 days<', $html);
        $this->assertStringContainsString('var SWARM =', $html);
        // Said plainly, rather than presented as a total.
        $this->assertStringContainsString('gone when the peer leaves', $html);
    }

    public function testLiveSwarmWithNothingReportedLoadsNoChartLibrary(): void
    {
        $html = view_admin_bandwidth_html($this->settings(), [], $this->torrents(), 'peers', '90', $this->windows(), 'tok', []);

        $this->assertStringContainsString('No peer is reporting any transfer', $html);
        $this->assertStringNotContainsString('chart.js', $html);
    }

    public function testTableColumnFollowsTheMetric(): void
    {
        $estimate = view_admin_bandwidth_html($this->settings(), [], $this->torrents(), 'events', '90', $this->windows(), 'tok');
        $live = view_admin_bandwidth_html($this->settings(), [], $this->torrents(), 'peers', '90', $this->windows(), 'tok', $this->swarm());

        // Matched as the header's own sort link: '>Bandwidth ' alone also matches
        // the footnote prose, so it passed whatever the header said.
        $this->assertStringContainsString('sort=bandwidth&amp;dir=asc">Bandwidth<', $estimate);
        $this->assertStringContainsString('sort=bandwidth&amp;dir=asc">Uploaded<', $live);
        $this->assertStringNotContainsString('>Uploaded<', $estimate);
        $this->assertStringNotContainsString('">Bandwidth<', $live);
    }

    public function testTableCarriesFilenameAndOwner(): void
    {
        $html = view_admin_bandwidth_html(
            $this->settings(),
            [['time' => 1788739200, 'completions' => 76, 'bytes' => 250263275520]],
            $this->torrents(),
            'events',
            '90',
            $this->windows(),
            'tok',
        );

        // Both are sort links now, so match the header text without assuming
        // what follows it.
        $this->assertStringContainsString('>Filename<', $html);
        $this->assertStringContainsString('>Owner<', $html);
        $this->assertStringContainsString('alpha.iso', $html);
        $this->assertStringContainsString('alice', $html);
    }

    public function testTableIsAGetFormNotAClientSideFilter(): void
    {
        // The listing is paged, so a browser-side filter would only ever search
        // the rendered page.
        $html = view_admin_bandwidth_html(
            $this->settings(),
            [['time' => 1788739200, 'completions' => 76, 'bytes' => 250263275520]],
            $this->torrents(),
            'events',
            '90',
            $this->windows(),
            'tok',
            [],
            1,
        );

        $this->assertStringContainsString('<form method="GET"', $html);
        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringNotContainsString('data-filter-table', $html);
    }

    public function testFilterMetricAndWindowSurviveInLinks(): void
    {
        $html = view_admin_bandwidth_html(
            $this->settings(),
            [],
            $this->torrents(),
            'events',
            '30',
            $this->windows(),
            'tok',
            [],
            250,
            100,
            100,
            'ubuntu',
        );

        // Sorting a search must not drop back to the whole table, nor to the
        // other metric.
        $this->assertStringContainsString('q=ubuntu', $html);
        $this->assertStringContainsString('metric=events', $html);
        $this->assertStringContainsString('days=30', $html);
        $this->assertStringContainsString('Showing 101&ndash;101 of 250', $html);
        $this->assertStringContainsString('>Next', $html);
    }

    public function testInfoHashNarrowsToOneTorrentAndSaysSo(): void
    {
        $html = view_admin_bandwidth_html(
            $this->settings(),
            [],
            $this->torrents(),
            'peers',
            '90',
            $this->windows(),
            'tok',
            [],
            1,
            0,
            100,
            '',
            str_repeat('a', 40),
        );

        $this->assertStringContainsString('Bandwidth for <b>Alpha</b>', $html);
        $this->assertStringContainsString('info_hash='.str_repeat('a', 40), $html);
        $this->assertStringContainsString('Show all torrents', $html);
    }

    public function testEmptyFilteredListOffersWayBack(): void
    {
        $html = view_admin_bandwidth_html(
            $this->settings(),
            [],
            [],
            'events',
            '90',
            $this->windows(),
            'tok',
            [],
            0,
            0,
            100,
            'nothing-matches',
        );

        $this->assertStringContainsString('No torrents match this filter.', $html);
        $this->assertStringNotContainsString('No torrents are registered.', $html);
    }

    public function testSelectedWindowIsMarked(): void
    {
        // $windows' numeric-looking keys are ints by the time they are read
        // back, so a strict compare against the string $window matches nothing
        // and leaves no window marked selected.
        $windows = [
            '30' => ['days' => 30, 'bucket' => 86400, 'label' => '30 days'],
            '90' => ['days' => 90, 'bucket' => 86400, 'label' => '90 days'],
            'all' => ['days' => 4000, 'bucket' => 2592000, 'label' => 'All time'],
        ];
        $series = [['time' => 1788739200, 'completions' => 76, 'bytes' => 250263275520]];

        foreach (['30', '90', 'all'] as $window) {
            $html = view_admin_bandwidth_html(
                $this->settings(),
                $series,
                $this->torrents(),
                'events',
                $window,
                $windows,
                'tok',
            );

            preg_match_all('/btn-xs is-on" href="[^"]*days=([^"&]*)"/', $html, $matches);
            $this->assertSame([$window], $matches[1], 'window '.$window);
        }
    }
}
