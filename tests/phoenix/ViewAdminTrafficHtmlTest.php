<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminTrafficHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.traffic.php';
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

    /** @return list<array{info_hash: string, name: string|null, size: int, downloads: int, estimated: int, uploaded: int, downloaded: int, peers: int}> */
    private function torrents(): array
    {
        return [[
            'info_hash' => str_repeat('a', 40), 'name' => 'Alpha', 'size' => 3335405568,
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
        $html = view_admin_traffic_html($this->settings(), $series, $this->torrents(), 'events', '90', $this->windows(), 'tok');

        $this->assertStringContainsString('canvas id="traffic-chart"', $html);
        $this->assertStringContainsString('var TRAFFIC =', $html);
        // Windows only make sense against a series.
        $this->assertStringContainsString('>90 days<', $html);
    }

    public function testLiveSwarmDrawsPeersNotAHistoryItCannotHave(): void
    {
        // The live counters are cumulative-since-client-start and vanish when a
        // peer leaves, so plotting them over time would claim something the
        // tracker cannot know.
        $html = view_admin_traffic_html($this->settings(), [], $this->torrents(), 'peers', '90', $this->windows(), 'tok', $this->swarm());

        $this->assertStringContainsString('canvas id="swarm-chart"', $html);
        $this->assertStringNotContainsString('traffic-chart', $html);
        $this->assertStringNotContainsString('>90 days<', $html);
        $this->assertStringContainsString('var SWARM =', $html);
        // Said plainly, rather than presented as a total.
        $this->assertStringContainsString('gone when the peer leaves', $html);
    }

    public function testLiveSwarmWithNothingReportedLoadsNoChartLibrary(): void
    {
        $html = view_admin_traffic_html($this->settings(), [], $this->torrents(), 'peers', '90', $this->windows(), 'tok', []);

        $this->assertStringContainsString('No peer is reporting any transfer', $html);
        $this->assertStringNotContainsString('chart.js', $html);
    }

    public function testTableColumnFollowsTheMetric(): void
    {
        $estimate = view_admin_traffic_html($this->settings(), [], $this->torrents(), 'events', '90', $this->windows(), 'tok');
        $live = view_admin_traffic_html($this->settings(), [], $this->torrents(), 'peers', '90', $this->windows(), 'tok', $this->swarm());

        $this->assertStringContainsString('>Traffic ', $estimate);
        $this->assertStringContainsString('>Uploaded ', $live);
    }
}
