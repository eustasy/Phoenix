<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ParseIpv6Test extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/functions/parse.ipv6.php';
	}

	public function testPlainIpv6(): void {
		$r = parse_ipv6('dead:beef::1234');
		$this->assertIsArray($r);
		$this->assertSame('dead:beef::1234', $r['ip']);
		$this->assertFalse($r['port']);
	}

	public function testBracketedWithPort(): void {
		$r = parse_ipv6('[dead:beef::1234]:12345');
		$this->assertIsArray($r);
		$this->assertSame('dead:beef::1234', $r['ip']);
		$this->assertSame(12345, $r['port']);
	}

	public function testBracketedWithoutPort(): void {
		$r = parse_ipv6('[dead:beef::1234]');
		$this->assertIsArray($r);
		$this->assertSame('dead:beef::1234', $r['ip']);
		$this->assertFalse($r['port']);
	}

	public function testRejectsIpv4(): void {
		$this->assertFalse(parse_ipv6('101.45.75.219'));
	}

	public function testRejectsGarbage(): void {
		$this->assertFalse(parse_ipv6('not an address'));
	}

	public function testRejectsEmpty(): void {
		$this->assertFalse(parse_ipv6(''));
	}

}
