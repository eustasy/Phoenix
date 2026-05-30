<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerAddressCandidatesTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/functions/peer.address.candidates.php';
	}

	public function testReturnsEmptyWhenNoSourcesAvailable(): void {
		$settings = array('external_ip' => false, 'honor_xff' => false);
		$this->assertSame(array(), peer_address_candidates($settings, array(), array()));
	}

	public function testReturnsRemoteAddrAlone(): void {
		$settings = array('external_ip' => false, 'honor_xff' => false);
		$result = peer_address_candidates($settings, array(), array('REMOTE_ADDR' => '10.0.0.1'));
		$this->assertSame(array('10.0.0.1'), $result);
	}

	public function testIgnoresGetParamsWhenExternalIpDisabled(): void {
		$settings = array('external_ip' => false, 'honor_xff' => false);
		$get = array('ip' => '1.1.1.1', 'ipv4' => '2.2.2.2', 'ipv6' => '::1');
		$server = array('REMOTE_ADDR' => '10.0.0.1');
		$this->assertSame(array('10.0.0.1'), peer_address_candidates($settings, $get, $server));
	}

	public function testIncludesGetIpsWhenExternalIpEnabled(): void {
		$settings = array('external_ip' => true, 'honor_xff' => false);
		$get = array('ip' => '1.1.1.1', 'ipv4' => '2.2.2.2', 'ipv6' => '::1');
		$server = array('REMOTE_ADDR' => '10.0.0.1');
		// Reversed: REMOTE_ADDR (last appended) first; client-supplied lowest priority.
		$this->assertSame(
			array('10.0.0.1', '::1', '2.2.2.2', '1.1.1.1'),
			peer_address_candidates($settings, $get, $server)
		);
	}

	public function testIgnoresProxyHeadersWhenHonorXffDisabled(): void {
		$settings = array('external_ip' => false, 'honor_xff' => false);
		$server = array(
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_CLIENT_IP'       => '5.5.5.5',
			'HTTP_X_FORWARDED_FOR' => '6.6.6.6',
		);
		$this->assertSame(array('10.0.0.1'), peer_address_candidates($settings, array(), $server));
	}

	public function testProxyHeadersTakePrecedenceWhenHonorXffEnabled(): void {
		$settings = array('external_ip' => false, 'honor_xff' => true);
		$server = array(
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_CLIENT_IP'       => '5.5.5.5',
			'HTTP_X_FORWARDED_FOR' => '6.6.6.6',
		);
		// Reversed: XFF first (most-trusted), CLIENT_IP next, REMOTE_ADDR last.
		$this->assertSame(
			array('6.6.6.6', '5.5.5.5', '10.0.0.1'),
			peer_address_candidates($settings, array(), $server)
		);
	}

	public function testXffMultiHopChainPicksLeftmost(): void {
		// Standard XFF chain: 'client, proxy1, proxy2'. The leftmost is the
		// originating client. The IP parsers cannot accept the raw header
		// (it isn't an IP), so we must split here.
		$settings = array('external_ip' => false, 'honor_xff' => true);
		$server = array(
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.5, 198.51.100.7, 10.0.0.1',
		);
		$this->assertSame(
			array('203.0.113.5', '10.0.0.1'),
			peer_address_candidates($settings, array(), $server)
		);
	}

	public function testXffWithExtraWhitespaceIsTrimmed(): void {
		$settings = array('external_ip' => false, 'honor_xff' => true);
		$server = array(
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '   203.0.113.5  ,198.51.100.7',
		);
		$this->assertSame(
			array('203.0.113.5', '10.0.0.1'),
			peer_address_candidates($settings, array(), $server)
		);
	}

	public function testXffSkippedWhenAllEntriesBlank(): void {
		$settings = array('external_ip' => false, 'honor_xff' => true);
		$server = array(
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => ' , ,  ',
		);
		$this->assertSame(
			array('10.0.0.1'),
			peer_address_candidates($settings, array(), $server)
		);
	}

	public function testClientIpHeaderIsAlsoSplit(): void {
		$settings = array('external_ip' => false, 'honor_xff' => true);
		$server = array(
			'REMOTE_ADDR'    => '10.0.0.1',
			'HTTP_CLIENT_IP' => '203.0.113.5, 198.51.100.7',
		);
		$this->assertSame(
			array('203.0.113.5', '10.0.0.1'),
			peer_address_candidates($settings, array(), $server)
		);
	}

}
