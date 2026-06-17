<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerExternalIpTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/peer.external.ip.php';
    }

    public function testReturnsIpv4WhenOnlyV4Resolved(): void
    {
        $peer = ['ipv4' => '203.0.113.5', 'ipv6' => false];
        $this->assertSame('203.0.113.5', peer_external_ip($peer, ['REMOTE_ADDR' => '203.0.113.5']));
    }

    public function testReturnsIpv6WhenOnlyV6Resolved(): void
    {
        $peer = ['ipv4' => false, 'ipv6' => '2001:db8::5'];
        $this->assertSame('2001:db8::5', peer_external_ip($peer, ['REMOTE_ADDR' => '2001:db8::5']));
    }

    public function testPrefersFamilyTheRequestArrivedOn(): void
    {
        $peer = ['ipv4' => '203.0.113.5', 'ipv6' => '2001:db8::5'];
        // Arrived over IPv6 -> v6 preferred.
        $this->assertSame('2001:db8::5', peer_external_ip($peer, ['REMOTE_ADDR' => '2001:db8::5']));
        // Arrived over IPv4 -> v4 preferred.
        $this->assertSame('203.0.113.5', peer_external_ip($peer, ['REMOTE_ADDR' => '203.0.113.5']));
    }

    public function testFallsBackWhenArrivalFamilyNotResolved(): void
    {
        // Arrived over IPv6 but only v4 resolved -> fall back to v4.
        $peer = ['ipv4' => '203.0.113.5', 'ipv6' => false];
        $this->assertSame('203.0.113.5', peer_external_ip($peer, ['REMOTE_ADDR' => '2001:db8::1']));
    }

    public function testReturnsFalseWhenNeitherResolved(): void
    {
        $this->assertFalse(peer_external_ip(['ipv4' => false, 'ipv6' => false], ['REMOTE_ADDR' => '203.0.113.5']));
    }

    public function testToleratesMissingRemoteAddr(): void
    {
        $peer = ['ipv4' => '203.0.113.5', 'ipv6' => false];
        $this->assertSame('203.0.113.5', peer_external_ip($peer, []));
    }
}
