<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class SanitizeMaybeBinaryToHexTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.sanitize.maybe_binary_to_hex.php';
	}

	public function testTwentyByteBinaryConvertsToFortyHex(): void {
		$result = maybe_binary_to_hex('whyonearthwouldidoth');
		$this->assertIsString($result);
		$this->assertSame(40, strlen($result));
	}

	public function testFortyHexPassesThroughSanitised(): void {
		$result = maybe_binary_to_hex('7768796f6e6561727468776f756c6469646f7468');
		$this->assertIsString($result);
		$this->assertSame(40, strlen($result));
	}

	public function testRejectsWrongLength(): void {
		$this->assertFalse(maybe_binary_to_hex('!"£$%^&*()_+-={}[]:@~;\'#'));
	}

	public function testUrlEncodedBinaryConvertsCorrectly(): void {
		$result = maybe_binary_to_hex('%fc%e7%20%afr%2a%81%3a%18LUP%a9%24%aa%a6%0a%8d%9a%f1');
		$this->assertSame('fce720af722a813a184c5550a924aaa60a8d9af1', $result);
	}

	public function testRejectsFortyCharNonHex(): void {
		// 40 chars but not hex: previously slipped through because the only
		// downstream sanitization was htmlentities, which doesn't escape '\'
		// and therefore couldn't prevent breaking out of single-quoted SQL.
		$this->assertFalse(maybe_binary_to_hex(str_repeat('\\', 40)));
		$this->assertFalse(maybe_binary_to_hex(str_repeat('z', 40)));
		$this->assertFalse(maybe_binary_to_hex(str_repeat("'", 40)));
	}

	public function testRejectsFortyCharWithMostlyHex(): void {
		// One non-hex character anywhere in an otherwise-hex string is enough.
		$this->assertFalse(maybe_binary_to_hex('00000000000000000000000000000000000000z0'));
	}

	public function testAcceptsUppercaseHex(): void {
		$result = maybe_binary_to_hex(str_repeat('A', 40));
		$this->assertSame(str_repeat('A', 40), $result);
	}

}
