<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/peer.chain.client.php';

class PeerChainClientTest extends TestCase
{
    public function testReturnsFirstUntrustedFromTheRight(): void
    {
        // [spoofed, real client, internal proxy] with the proxy trusted.
        $this->assertSame(
            '203.0.113.5',
            peer_chain_client(['1.2.3.4', '203.0.113.5', '10.0.0.8'], ['10.0.0.0/8']),
        );
    }

    public function testEmptyTrustedReturnsRightmost(): void
    {
        $this->assertSame('2.2.2.2', peer_chain_client(['1.1.1.1', '2.2.2.2'], []));
        $this->assertSame('203.0.113.5', peer_chain_client(['203.0.113.5'], []));
    }

    public function testAllTrustedReturnsNull(): void
    {
        $this->assertNull(peer_chain_client(['10.0.0.1', '10.0.0.2'], ['10.0.0.0/8']));
    }

    public function testEmptyChainReturnsNull(): void
    {
        $this->assertNull(peer_chain_client([], ['10.0.0.0/8']));
    }

    public function testIpv6TrustedProxySkipped(): void
    {
        $this->assertSame(
            '203.0.113.5',
            peer_chain_client(['203.0.113.5', '2001:db8::8'], ['2001:db8::/32']),
        );
    }

    public function testTrustedRangeOnlyMatchesItsOwnFamily(): void
    {
        // A v4 CIDR must not match the v6 entry, so the v6 client is returned.
        $this->assertSame(
            '2001:db8::5',
            peer_chain_client(['2001:db8::5', '10.0.0.8'], ['10.0.0.0/8']),
        );
    }
}
