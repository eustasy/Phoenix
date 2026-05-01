<?php

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class TrackerValidateInfoHashesTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.tracker.validate.info.hashes.php';
	}

	public function testAllHashesAllowed() {
		$allowed = [
			'0123456789abcdef0123456789abcdef01234567',
			'1111111111111111111111111111111111111111',
			'2222222222222222222222222222222222222222',
		];
		$requested = [
			'0123456789abcdef0123456789abcdef01234567',
			'1111111111111111111111111111111111111111',
		];
		$this->assertTrue(tracker_validate_info_hashes($requested, $allowed));
	}

	public function testSomeHashesNotAllowed() {
		$allowed = [
			'0123456789abcdef0123456789abcdef01234567',
			'1111111111111111111111111111111111111111',
		];
		$requested = [
			'0123456789abcdef0123456789abcdef01234567',
			'2222222222222222222222222222222222222222', // not allowed
		];
		$this->assertFalse(tracker_validate_info_hashes($requested, $allowed));
	}

	public function testEmptyRequestedArray() {
		$allowed = [
			'0123456789abcdef0123456789abcdef01234567',
		];
		$requested = [];
		$this->assertTrue(tracker_validate_info_hashes($requested, $allowed));
	}

	public function testSingleHashAllowed() {
		$allowed = [
			'0123456789abcdef0123456789abcdef01234567',
		];
		$requested = [
			'0123456789abcdef0123456789abcdef01234567',
		];
		$this->assertTrue(tracker_validate_info_hashes($requested, $allowed));
	}

	public function testSingleHashNotAllowed() {
		$allowed = [
			'0123456789abcdef0123456789abcdef01234567',
		];
		$requested = [
			'1111111111111111111111111111111111111111',
		];
		$this->assertFalse(tracker_validate_info_hashes($requested, $allowed));
	}

	public function testFirstHashNotAllowed() {
		$allowed = [
			'1111111111111111111111111111111111111111',
			'2222222222222222222222222222222222222222',
		];
		$requested = [
			'0123456789abcdef0123456789abcdef01234567', // not allowed
			'1111111111111111111111111111111111111111',
		];
		$this->assertFalse(tracker_validate_info_hashes($requested, $allowed));
	}

	public function testLastHashNotAllowed() {
		$allowed = [
			'0123456789abcdef0123456789abcdef01234567',
			'1111111111111111111111111111111111111111',
		];
		$requested = [
			'0123456789abcdef0123456789abcdef01234567',
			'2222222222222222222222222222222222222222', // not allowed
		];
		$this->assertFalse(tracker_validate_info_hashes($requested, $allowed));
	}
}
