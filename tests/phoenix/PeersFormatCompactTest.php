<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeersFormatCompactTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/functions/peers.format.compact.php';
	}

	private function compactv4(string $ip, int $port): string {
		return bin2hex(pack('Nn', ip2long($ip), $port));
	}

	private function compactv6(string $ip, int $port): string {
		return bin2hex(inet_pton($ip).pack('n', $port));
	}

	public function testEmptyRowsReturnsEmptyStrings(): void {
		$result = peers_format_compact(array());
		$this->assertSame('', $result['v4']);
		$this->assertSame('', $result['v6']);
	}

	public function testIpv4OnlyRowAppendsToV4Only(): void {
		$rows = array(
			array('compactv4' => $this->compactv4('192.0.2.1', 6881), 'compactv6' => ''),
		);
		$result = peers_format_compact($rows);
		$this->assertSame(pack('Nn', ip2long('192.0.2.1'), 6881), $result['v4']);
		$this->assertSame('', $result['v6']);
	}

	public function testIpv6OnlyRowAppendsToV6Only(): void {
		$rows = array(
			array('compactv4' => '', 'compactv6' => $this->compactv6('2001:db8::1', 6881)),
		);
		$result = peers_format_compact($rows);
		$this->assertSame('', $result['v4']);
		$this->assertSame(inet_pton('2001:db8::1').pack('n', 6881), $result['v6']);
	}

	public function testDualStackRowAppendsToBoth(): void {
		$rows = array(
			array(
				'compactv4' => $this->compactv4('192.0.2.1', 6881),
				'compactv6' => $this->compactv6('2001:db8::1', 6881),
			),
		);
		$result = peers_format_compact($rows);
		$this->assertSame(6,  strlen($result['v4']));
		$this->assertSame(18, strlen($result['v6']));
	}

	public function testMultipleRowsAreConcatenated(): void {
		$rows = array(
			array('compactv4' => $this->compactv4('192.0.2.1', 6881), 'compactv6' => ''),
			array('compactv4' => $this->compactv4('192.0.2.2', 6882), 'compactv6' => ''),
		);
		$result = peers_format_compact($rows);
		$this->assertSame(12, strlen($result['v4']));
		$expected = pack('Nn', ip2long('192.0.2.1'), 6881).pack('Nn', ip2long('192.0.2.2'), 6882);
		$this->assertSame($expected, $result['v4']);
	}

}
