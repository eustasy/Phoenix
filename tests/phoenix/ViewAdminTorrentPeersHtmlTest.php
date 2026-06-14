<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminTorrentPeersHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.torrent.peers.php';
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
    private function peer(array $overrides = []): array
    {
        return array_merge([
            'peer_id' => str_repeat('a', 40),
            'ipv4' => '81.78.207.83',
            'ipv6' => '',
            'portv4' => 51413,
            'portv6' => 0,
            'uploaded' => 1024,
            'downloaded' => 2048,
            'left' => 0,
            'state' => 1,
            'updated' => 1700000000,
            'client' => 'Transmission 4.1.1.0',
        ], $overrides);
    }

    public function testRendersPeerRowWithClientStateAndAddress(): void
    {
        $html = view_admin_torrent_peers_html($this->settings(), str_repeat('a', 40), 'My Torrent', [$this->peer()], 'tok');

        $this->assertStringContainsString('<title>Phoenix Admin: Peers</title>', $html);
        $this->assertStringContainsString('My Torrent', $html);
        $this->assertStringContainsString('Transmission 4.1.1.0', $html);
        $this->assertStringContainsString('81.78.207.83:51413', $html);
        $this->assertStringContainsString('Seeding', $html);
        $this->assertStringContainsString('?page=torrents', $html);
    }

    public function testLeechingStateAndIpv6Address(): void
    {
        $html = view_admin_torrent_peers_html(
            $this->settings(),
            str_repeat('a', 40),
            'My Torrent',
            [$this->peer(['state' => 0, 'ipv4' => '', 'ipv6' => '2001:db8::1', 'portv6' => 6881])],
            'tok',
        );
        $this->assertStringContainsString('Leeching', $html);
        $this->assertStringContainsString('[2001:db8::1]:6881', $html);
    }

    public function testEmptySwarmShowsNoPeers(): void
    {
        $html = view_admin_torrent_peers_html($this->settings(), str_repeat('a', 40), 'My Torrent', [], 'tok');
        $this->assertStringContainsString('No active peers.', $html);
    }

    public function testTitleFallsBackToInfoHashWhenUnnamed(): void
    {
        // An unregistered swarm has no registry name → show the hash.
        $html = view_admin_torrent_peers_html($this->settings(), str_repeat('b', 40), null, [], 'tok');
        $this->assertStringContainsString('<code>'.str_repeat('b', 40).'</code>', $html);
    }

    public function testMarksTorrentsNavActive(): void
    {
        // The drill-down lives beneath Torrents, so that nav link stays current.
        $html = view_admin_torrent_peers_html($this->settings(), str_repeat('a', 40), 'X', [], 'tok');
        $this->assertStringContainsString('href="?page=torrents" class="nav-link current" aria-current="page"', $html);
    }

    public function testEscapesClientLabel(): void
    {
        $html = view_admin_torrent_peers_html(
            $this->settings(),
            str_repeat('a', 40),
            'X',
            [$this->peer(['client' => '<script>x</script>'])],
            'tok',
        );
        $this->assertStringNotContainsString('<script>x</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
