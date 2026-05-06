<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerFormatBencodeTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/functions/function.peer.format.bencode.php';
	}

	public function testIpv4WithPeerId(): void {
		$row = array(
			'ipv4'    => '1.2.3.4',
			'ipv6'    => null,
			'portv4'  => 12345,
			'portv6'  => 0,
			'peer_id' => str_repeat('00', 20),
		);
		// BEP 3 dict keys must be sorted as raw bytes: 'ip' < 'peer id' < 'port'.
		$expected = 'd2:ip7:1.2.3.47:peer id20:'.hex2bin(str_repeat('00', 20)).'4:porti12345ee';
		$this->assertSame($expected, peer_format_bencode($row, true));
	}

	public function testIpv4WithoutPeerId(): void {
		$row = array(
			'ipv4'    => '1.2.3.4',
			'ipv6'    => null,
			'portv4'  => 12345,
			'portv6'  => 0,
			'peer_id' => str_repeat('00', 20),
		);
		$this->assertSame('d2:ip7:1.2.3.44:porti12345ee', peer_format_bencode($row, false));
	}

	public function testIpv6Only(): void {
		$row = array(
			'ipv4'    => null,
			'ipv6'    => 'dead::1',
			'portv4'  => 0,
			'portv6'  => 12345,
			'peer_id' => str_repeat('ff', 20),
		);
		$this->assertSame('d2:ip7:dead::14:porti12345ee', peer_format_bencode($row, false));
	}

	public function testIpv4TakesPrecedenceWhenBothSet(): void {
		$row = array(
			'ipv4'    => '1.2.3.4',
			'ipv6'    => 'dead::1',
			'portv4'  => 12345,
			'portv6'  => 54321,
			'peer_id' => str_repeat('00', 20),
		);
		$this->assertSame('d2:ip7:1.2.3.44:porti12345ee', peer_format_bencode($row, false));
	}

	public function testReturnsEmptyStringWhenNoAddress(): void {
		// Avoid emitting a stray closing 'e' for rows that have neither family;
		// the row should simply be skipped from the response.
		$row = array(
			'ipv4'    => null,
			'ipv6'    => null,
			'portv4'  => 0,
			'portv6'  => 0,
			'peer_id' => str_repeat('00', 20),
		);
		$this->assertSame('', peer_format_bencode($row, true));
		$this->assertSame('', peer_format_bencode($row, false));
	}

	public function testKeyOrderIsLexicographic(): void {
		$row = array(
			'ipv4'    => '1.2.3.4',
			'ipv6'    => null,
			'portv4'  => 6881,
			'portv6'  => 0,
			'peer_id' => str_repeat('aa', 20),
		);
		$out = peer_format_bencode($row, true);
		$ip      = strpos($out, '2:ip');
		$peer_id = strpos($out, '7:peer id');
		$port    = strpos($out, '4:port');
		$this->assertNotFalse($ip);
		$this->assertNotFalse($peer_id);
		$this->assertNotFalse($port);
		$this->assertLessThan($peer_id, $ip);
		$this->assertLessThan($port,    $peer_id);
	}

}
