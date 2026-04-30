<?php
declare(strict_types=1);

namespace Phoenix\Tests;

class SanitizeTrackerTest extends PhoenixTestCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.sanitize.tracker.php';
	}

	public function testSingleInfoHashAndPeerId(): void
	{
		$info_hash = str_repeat('a', 20);
		$peer_id = str_repeat('b', 20);
		$query = 'info_hash=' . rawurlencode($info_hash) . '&peer_id=' . rawurlencode($peer_id);
		$result = sanitize_tracker_params($query);
		$this->assertCount(1, $result['info_hashes']);
		$this->assertSame(bin2hex($info_hash), $result['info_hash']);
		$this->assertSame(bin2hex($peer_id), $result['peer_id']);
	}

	public function testMultipleInfoHashes(): void
	{
		$ih1 = str_repeat('a', 20);
		$ih2 = str_repeat('b', 20);
		$query = 'info_hash=' . rawurlencode($ih1) . '&info_hash=' . rawurlencode($ih2);
		$result = sanitize_tracker_params($query);
		$this->assertCount(2, $result['info_hashes']);
		$this->assertSame(bin2hex($ih1), $result['info_hashes'][0]);
		$this->assertSame(bin2hex($ih2), $result['info_hashes'][1]);
		$this->assertSame(bin2hex($ih1), $result['info_hash']);
	}

	public function testHexInput(): void
	{
		$hex = str_repeat('ab', 20); // 40 chars
		$query = 'info_hash=' . $hex;
		$result = sanitize_tracker_params($query);
		$this->assertSame($hex, $result['info_hash']);
	}

	public function testNoParams(): void
	{
		$result = sanitize_tracker_params('');
		$this->assertFalse($result['info_hash']);
		$this->assertFalse($result['peer_id']);
		$this->assertSame([], $result['info_hashes']);
	}
}
