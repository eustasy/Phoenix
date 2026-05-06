<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerSelectTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/model/peer.select.php';
		require_once __DIR__.'/../../src/model/peer.insert.php';
	}

	protected function tearDown(): void {
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
	}

	/** @return array<string, mixed> */
	private function fixturePeer(string $info_hash, string $peer_id): array {
		return array(
			'info_hash'  => $info_hash,
			'peer_id'    => $peer_id,
			'state'      => 0,
			'left'       => 0,
			'uploaded'   => 0,
			'downloaded' => 0,
			'ipv4'       => '',
			'ipv6'       => '',
			'port'       => '',
			'portv4'     => '0',
			'portv6'     => '0',
		);
	}

	public function testReturnsFalseWhenNoMatchingRow(): void {
		$peer = $this->fixturePeer('__TEST_PS_MISSING__', '__TEST_PS_MISSING__');
		$this->assertFalse(peer_select(self::$connection, self::$settings, $peer));
	}

	public function testReturnsRowWhenPeerExists(): void {
		$peer = $this->fixturePeer('__TEST_PS_EXISTS__', '__TEST_PS_EXISTS__');
		peer_insert(self::$connection, self::$settings, self::$time, $peer);

		$result = peer_select(self::$connection, self::$settings, $peer);

		$this->assertIsArray($result);
		$this->assertSame('__TEST_PS_EXISTS__', $result['info_hash']);
		$this->assertSame('__TEST_PS_EXISTS__', $result['peer_id']);
	}

	public function testRequiresBothInfoHashAndPeerIdToMatch(): void {
		// Insert under one peer_id, look up under another with the same info_hash.
		$inserted = $this->fixturePeer('__TEST_PS_HASH__', '__TEST_PS_PEER_A__');
		peer_insert(self::$connection, self::$settings, self::$time, $inserted);

		$lookup = $this->fixturePeer('__TEST_PS_HASH__', '__TEST_PS_PEER_B__');
		$this->assertFalse(peer_select(self::$connection, self::$settings, $lookup));
	}

}
