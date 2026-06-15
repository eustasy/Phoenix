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
        return ['phoenix_version' => 'Phoenix Test v.0', 'admin_password' => 'hash'];
    }

    public function testRendersBaseDocument(): void
    {
        $html = view_admin_peers_html($this->settings(), 'tok');
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Peers</title>', $html);
        $this->assertStringContainsString('<a href="?page=peers" class="is-active" aria-current="page">', $html);
    }

    public function testFlagsItAsUnwiredPreview(): void
    {
        // The swarm-wide listing is UI-only for now; the banner points to the
        // live per-torrent drill-down instead.
        $html = view_admin_peers_html($this->settings(), 'tok');
        $this->assertStringContainsString('Preview', $html);
        $this->assertStringContainsString('not wired to the tracker', $html);
        $this->assertStringContainsString('?page=torrents', $html);
    }

    public function testRendersPeerTable(): void
    {
        $html = view_admin_peers_html($this->settings(), 'tok');
        $this->assertStringContainsString('id="tbl-peers"', $html);
        $this->assertStringContainsString('Seeding', $html);
        $this->assertStringContainsString('Leeching', $html);
    }
}
