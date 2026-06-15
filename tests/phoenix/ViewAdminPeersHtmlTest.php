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
            'client' => 'Transmission 4.1.1.0',
        ], $overrides);
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
}
