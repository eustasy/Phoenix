<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerFormatBencodeTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.peer.format.bencode.php';
	}

	public function testIpv4WithPeerId(): void {
		$row = array(
			'ipv4'    => '1.2.3.4',
			'ipv6'    => null,
			'portv4'  => 12345,
			'portv6'  => 0,
			'peer_id' => str_repeat('00', 20),
		);
		$expected = 'd2:ip7:1.2.3.44:porti12345e7:peer id20:'.hex2bin(str_repeat('00', 20)).'e';
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

}
