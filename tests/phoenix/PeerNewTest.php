<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerNewTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.peer.new.php';
	}

	protected function tearDown(): void {
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fixturePeer(): array {
		return array(
			'info_hash'  => '__TEST_1__',
			'peer_id'    => '__TEST_1__',
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

	public function testInsertsNewPeer(): void {
		$this->assertTrue(peer_new(self::$connection, self::$settings, self::$time, $this->fixturePeer()));
	}

	public function testReplacesExistingPeer(): void {
		$peer = $this->fixturePeer();
		peer_new(self::$connection, self::$settings, self::$time, $peer);
		$this->assertTrue(peer_new(self::$connection, self::$settings, self::$time, $peer));
	}

}
