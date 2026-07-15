<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerResolveAddressesTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/parse.ipv4.php';
        require_once __DIR__.'/../../src/functions/parse.ipv6.php';
        require_once __DIR__.'/../../src/functions/peer.resolve.addresses.php';
    }

    public function testEmptyListYieldsAllFalse(): void
    {
        $this->assertSame(
            ['ipv4' => false, 'ipv6' => false, 'portv4' => false, 'portv6' => false],
            peer_resolve_addresses([]),
        );
    }

    public function testResolvesIpv4WithPort(): void
    {
        $result = peer_resolve_addresses(['192.0.2.1:6881']);
        $this->assertSame('192.0.2.1', $result['ipv4']);
        $this->assertSame(6881, $result['portv4']);
        $this->assertFalse($result['ipv6']);
        $this->assertFalse($result['portv6']);
    }

    public function testResolvesIpv6WithPort(): void
    {
        $result = peer_resolve_addresses(['[2001:db8::1]:6881']);
        $this->assertSame('2001:db8::1', $result['ipv6']);
        $this->assertSame(6881, $result['portv6']);
        $this->assertFalse($result['ipv4']);
        $this->assertFalse($result['portv4']);
    }

    public function testResolvesMixedListWithBothFamilies(): void
    {
        $result = peer_resolve_addresses([
            '192.0.2.1:6881',
            '[2001:db8::1]:6882',
        ]);
        $this->assertSame('192.0.2.1', $result['ipv4']);
        $this->assertSame(6881, $result['portv4']);
        $this->assertSame('2001:db8::1', $result['ipv6']);
        $this->assertSame(6882, $result['portv6']);
    }

    public function testFirstValidIpv4WinsOverLaterCandidates(): void
    {
        $result = peer_resolve_addresses([
            '192.0.2.1:6881',
            '198.51.100.1:9999',
        ]);
        $this->assertSame('192.0.2.1', $result['ipv4']);
        $this->assertSame(6881, $result['portv4']);
    }

    public function testInvalidAddressesReturnFalse(): void
    {
        $result = peer_resolve_addresses([
            'not-an-ip',
            'definitely.not.an.ip:80',
        ]);
        $this->assertFalse($result['ipv4']);
        $this->assertFalse($result['ipv6']);
        $this->assertFalse($result['portv4']);
        $this->assertFalse($result['portv6']);
    }

    public function testAddressWithoutPortLeavesPortFalse(): void
    {
        $result = peer_resolve_addresses(['192.0.2.1']);
        $this->assertSame('192.0.2.1', $result['ipv4']);
        $this->assertFalse($result['portv4']);
    }

    ////	reject_private flag

    public function testPrivateAddressKeptWhenRejectDisabled(): void
    {
        // Default behaviour: a private REMOTE_ADDR is accepted as-is.
        $result = peer_resolve_addresses(['10.0.0.1:6881']);
        $this->assertSame('10.0.0.1', $result['ipv4']);
        $this->assertSame(6881, $result['portv4']);
    }

    public function testPrivateAddressSkippedWhenRejectEnabled(): void
    {
        $result = peer_resolve_addresses(['10.0.0.1:6881'], true);
        $this->assertFalse($result['ipv4']);
        $this->assertFalse($result['portv4']);
    }

    public function testPrivateFallsThroughToPublicCandidate(): void
    {
        // The BEP 3 NAT/proxy case: candidate order (already reversed by
        // peer_address_candidates) puts the private REMOTE_ADDR first and a
        // public client-declared IP (allow_client_ip) after it. With rejection on,
        // the private address is skipped and the public one is used.
        $result = peer_resolve_addresses(['10.0.0.1:6881', '8.8.8.8:51413'], true);
        $this->assertSame('8.8.8.8', $result['ipv4']);
        $this->assertSame(51413, $result['portv4']);
    }

    public function testUlaIpv6SkippedWhenRejectEnabled(): void
    {
        $result = peer_resolve_addresses(['[fd00::1]:6881'], true);
        $this->assertFalse($result['ipv6']);
        $this->assertFalse($result['portv6']);
    }

    public function testPublicIpv6KeptWhenRejectEnabled(): void
    {
        $result = peer_resolve_addresses(['[2606:4700:4700::1111]:6881'], true);
        $this->assertSame('2606:4700:4700::1111', $result['ipv6']);
        $this->assertSame(6881, $result['portv6']);
    }

}
