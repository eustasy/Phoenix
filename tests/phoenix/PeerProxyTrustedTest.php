<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/peer.proxy.trusted.php';

class PeerProxyTrustedTest extends TestCase
{
    public function testEmptyTrustedFailsClosedByDefault(): void
    {
        // empty trusted_proxies trusts no one unless opted in.
        $this->assertFalse(peer_proxy_trusted(['REMOTE_ADDR' => '203.0.113.9'], [], false));
        $this->assertFalse(peer_proxy_trusted([], [], false));
    }

    public function testEmptyTrustedHonoredWithAllowAnyProxy(): void
    {
        $this->assertTrue(peer_proxy_trusted(['REMOTE_ADDR' => '203.0.113.9'], [], true));
        // Returns the opt-in even without a REMOTE_ADDR (short-circuits first).
        $this->assertTrue(peer_proxy_trusted([], [], true));
    }

    public function testRemoteAddrInTrustedRange(): void
    {
        $this->assertTrue(peer_proxy_trusted(['REMOTE_ADDR' => '10.0.0.5'], ['10.0.0.0/8'], false));
    }

    public function testRemoteAddrOutsideTrustedRange(): void
    {
        $this->assertFalse(peer_proxy_trusted(['REMOTE_ADDR' => '203.0.113.9'], ['10.0.0.0/8'], false));
    }

    public function testMissingRemoteAddrWithListedProxies(): void
    {
        $this->assertFalse(peer_proxy_trusted([], ['10.0.0.0/8'], false));
    }

    public function testIpv6RemoteAddrInIpv6Range(): void
    {
        $this->assertTrue(peer_proxy_trusted(['REMOTE_ADDR' => '2001:db8::9'], ['2001:db8::/32'], false));
    }

    public function testAllowAnyProxyIgnoredWhenRangesListed(): void
    {
        // allow_any_proxy only governs the empty-list case; a listed range still
        // requires REMOTE_ADDR to fall inside it.
        $this->assertFalse(peer_proxy_trusted(['REMOTE_ADDR' => '203.0.113.9'], ['10.0.0.0/8'], true));
    }
}
