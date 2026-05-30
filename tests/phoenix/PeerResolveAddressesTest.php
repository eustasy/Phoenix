<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerResolveAddressesTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/functions/parse.ipv4.php';
		require_once __DIR__.'/../../src/functions/parse.ipv6.php';
		require_once __DIR__.'/../../src/functions/peer.resolve.addresses.php';
	}

	public function testEmptyListYieldsAllFalse(): void {
		$this->assertSame(
			array('ipv4' => false, 'ipv6' => false, 'portv4' => false, 'portv6' => false),
			peer_resolve_addresses(array())
		);
	}

	public function testResolvesIpv4WithPort(): void {
		$result = peer_resolve_addresses(array('192.0.2.1:6881'));
		$this->assertSame('192.0.2.1', $result['ipv4']);
		$this->assertSame(6881, $result['portv4']);
		$this->assertFalse($result['ipv6']);
		$this->assertFalse($result['portv6']);
	}

	public function testResolvesIpv6WithPort(): void {
		$result = peer_resolve_addresses(array('[2001:db8::1]:6881'));
		$this->assertSame('2001:db8::1', $result['ipv6']);
		$this->assertSame(6881, $result['portv6']);
		$this->assertFalse($result['ipv4']);
		$this->assertFalse($result['portv4']);
	}

	public function testResolvesMixedListWithBothFamilies(): void {
		$result = peer_resolve_addresses(array(
			'192.0.2.1:6881',
			'[2001:db8::1]:6882',
		));
		$this->assertSame('192.0.2.1', $result['ipv4']);
		$this->assertSame(6881, $result['portv4']);
		$this->assertSame('2001:db8::1', $result['ipv6']);
		$this->assertSame(6882, $result['portv6']);
	}

	public function testFirstValidIpv4WinsOverLaterCandidates(): void {
		$result = peer_resolve_addresses(array(
			'192.0.2.1:6881',
			'198.51.100.1:9999',
		));
		$this->assertSame('192.0.2.1', $result['ipv4']);
		$this->assertSame(6881, $result['portv4']);
	}

	public function testInvalidAddressesReturnFalse(): void {
		$result = peer_resolve_addresses(array(
			'not-an-ip',
			'definitely.not.an.ip:80',
		));
		$this->assertFalse($result['ipv4']);
		$this->assertFalse($result['ipv6']);
		$this->assertFalse($result['portv4']);
		$this->assertFalse($result['portv6']);
	}

	public function testAddressWithoutPortLeavesPortFalse(): void {
		$result = peer_resolve_addresses(array('192.0.2.1'));
		$this->assertSame('192.0.2.1', $result['ipv4']);
		$this->assertFalse($result['portv4']);
	}

}
