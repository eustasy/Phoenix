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
        return ['phoenix_version' => 'Phoenix Test v.0', 'admin_password' => 'hash'];
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

    public function testHistoricalMetricHasNoVersionAxis(): void
    {
        // The ledger stores the label written at the time, so a long-running
        // tracker carries both "Transmission" and "Transmission 4.1.3.0" for the
        // same client. They are folded to family, and the page says so rather
        // than leaving the reader to wonder where versions went.
        $families = ['Transmission' => ['' => 364237], 'qBittorrent' => ['' => 357158]];
        $html = view_admin_clients_html($this->settings(), 'events', $families, 721395, 'tok');

        $this->assertStringContainsString('>Downloads ', $html);
        $this->assertStringNotContainsString('4.1.3.0', $html);
        $this->assertStringContainsString('folded together', $html);
        $this->assertStringContainsString('metric=live', $html);
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
}
