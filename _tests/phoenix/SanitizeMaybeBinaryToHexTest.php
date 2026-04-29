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

}
