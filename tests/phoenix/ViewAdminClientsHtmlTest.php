<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminClientsHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.clients.php';
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return ['phoenix_version' => 'Phoenix Test v.0',
            'phoenix_release' => 'Testing', 'admin_password' => 'hash'];
    }

    public function testLiveMetricBreaksFamiliesDownByVersion(): void
    {
        $families = ['Transmission' => ['4.1.3.0' => 49, '3.0.0.0' => 33], 'qBittorrent' => ['5.2.3.0' => 52]];
        $html = view_admin_clients_html($this->settings(), 'live', $families, 134, 'tok');

        $this->assertStringContainsString('<canvas id="clients-chart">', $html);
        $this->assertStringContainsString('4.1.3.0', $html);
        $this->assertStringContainsString('>Peers ', $html);
        $this->assertStringContainsString('metric=events', $html);
    }

    public function testHistoricalMetricShowsVersionsWhereRecorded(): void
    {
        // A long-running tracker carries labels from more than one era of its
        // detector, so a family has a versioned part and an unversioned
        // remainder — both belong to the one client.
        $families = ['Transmission' => ['' => 364237, '4.1.3.0' => 2]];
        $html = view_admin_clients_html($this->settings(), 'events', $families, 364239, 'tok');

        $this->assertStringContainsString('>Downloads ', $html);
        $this->assertStringContainsString('4.1.3.0', $html);
        $this->assertStringContainsString('metric=live', $html);
    }

    public function testFamilyWithNoRecordedVersionsListsNone(): void
    {
        $html = view_admin_clients_html($this->settings(), 'events', ['BBtor' => ['' => 32163]], 32163, 'tok');

        $this->assertStringContainsString('BBtor', $html);
        $this->assertStringContainsString('&mdash;', $html);
    }

    public function testChartHeightScalesWithTheNumberOfClients(): void
    {
        // A fixed height squeezes a long list until Chart.js drops labels, and
        // the all-time view carries every client the tracker has ever seen.
        $few = [];
        $many = [];
        for ($i = 0; $i < 3; $i++) {
            $few['Client'.$i] = ['' => 10];
        }
        for ($i = 0; $i < 40; $i++) {
            $many['Client'.$i] = ['' => 10];
        }

        preg_match('/ph-chart" style="height: (\d+)px/', view_admin_clients_html($this->settings(), 'events', $few, 30, 'tok'), $a);
        preg_match('/ph-chart" style="height: (\d+)px/', view_admin_clients_html($this->settings(), 'events', $many, 400, 'tok'), $b);

        $this->assertNotEmpty($a);
        $this->assertNotEmpty($b);
        // A short list keeps a sensible minimum; a long one grows.
        $this->assertSame(240, (int) $a[1]);
        $this->assertGreaterThan(1000, (int) $b[1]);
    }

    public function testShareIsOfTheSelectedMetricsTotal(): void
    {
        $html = view_admin_clients_html($this->settings(), 'live', ['A' => ['' => 25], 'B' => ['' => 75]], 100, 'tok');

        $this->assertStringContainsString('25.0%', $html);
        $this->assertStringContainsString('75.0%', $html);
    }

    public function testEmptyStateExplainsTheSelectedSource(): void
    {
        $live = view_admin_clients_html($this->settings(), 'live', [], 0, 'tok');
        $events = view_admin_clients_html($this->settings(), 'events', [], 0, 'tok');

        $this->assertStringContainsString('No peer is currently announcing', $live);
        $this->assertStringContainsString('stats_enabled', $events);
        // No chart, and no reason to load the library either.
        $this->assertStringNotContainsString('clients-chart', $live);
        $this->assertStringNotContainsString('chart.js', $live);
    }

    public function testTableGroupsVersionsByMajorWithBreakouts(): void
    {
        $html = view_admin_clients_html(
            $this->settings(),
            'live',
            ['Transmission' => ['4.1.3.0' => 49, '4.0.6' => 12, '3.0.0' => 7]],
            68,
            'tok',
        );

        // The major answers "how much of the swarm is on 4"; the tooltip
        // answers "which 4" without spending a line on it.
        $this->assertStringContainsString('>Major versions<', $html);
        // One build per line, so a family with a dozen point releases stays
        // readable instead of running off the screen.
        $this->assertStringContainsString("title=\"4.1.3.0 ×49\n4.0.6 ×12\">4</abbr>", $html);
        $this->assertStringContainsString('&times;61', $html);
    }

    public function testBareMajorGetsNoRedundantTooltip(): void
    {
        // One build under a major, and that build IS the major, so a tooltip
        // would only repeat what is already on screen.
        $html = view_admin_clients_html($this->settings(), 'live', ['Deluge' => ['2' => 9]], 9, 'tok');

        $this->assertStringNotContainsString('<abbr', $html);
    }

    public function testChartIsGroupedByMajor(): void
    {
        $html = view_admin_clients_html(
            $this->settings(),
            'live',
            ['Transmission' => ['4.1.3.0' => 49, '4.0.6' => 12, '3.0.0' => 7]],
            68,
            'tok',
        );

        // Charted by exact version, a family's bar fragments into a sliver per
        // point release.
        $this->assertStringContainsString('"Transmission":{"4":61,"3":7}', $html);
    }

    public function testChartSubBarsAreOrderedByVersionNotCount(): void
    {
        // 5 is the smallest group and still leads, 4 the biggest and still
        // follows — so this fixture can only pass under version ordering.
        $html = view_admin_clients_html(
            $this->settings(),
            'live',
            ['Transmission' => ['4.1.3.0' => 49, '4.0.6' => 12, '3.0.0' => 7, '5.0.1' => 2]],
            70,
            'tok',
        );

        $this->assertStringContainsString('"Transmission":{"5":2,"4":61,"3":7}', $html);
        // And the table reads the same way, so chart and table agree.
        $this->assertMatchesRegularExpression('/>5<\/abbr>.*>4<\/abbr>.*>3<\/abbr>/s', $html);
    }
}
