<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerSwarmCountsTest extends PhoenixTestCase {

	private const HASH       = '__TEST_PSC_HASH__';
	private const OTHER_HASH = '__TEST_PSC_OTHER_HASH__';

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['model'].'peers.count.swarm.php';
		require_once self::$settings['model'].'peer.insert.php';
	}

	protected function tearDown(): void {
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
	}

	public function testEmptySwarmReturnsZeroCounts(): void {
		$counts = peers_count_swarm(self::$connection, self::$settings, self::HASH, self::$time - 100);
		$this->assertSame(0, $counts['complete']);
		$this->assertSame(0, $counts['incomplete']);
	}

	public function testCountsSeedersAndLeechers(): void {
		$this->insertPeer(self::HASH, '__TEST_PSC_S1__', 1, self::$time);
		$this->insertPeer(self::HASH, '__TEST_PSC_S2__', 1, self::$time);
		$this->insertPeer(self::HASH, '__TEST_PSC_L1__', 0, self::$time);
		$this->insertPeer(self::HASH, '__TEST_PSC_L2__', 0, self::$time);
		$this->insertPeer(self::HASH, '__TEST_PSC_L3__', 0, self::$time);

		$counts = peers_count_swarm(self::$connection, self::$settings, self::HASH, self::$time - 100);
		$this->assertSame(2, $counts['complete']);
		$this->assertSame(3, $counts['incomplete']);
	}

	public function testExcludesStalePeers(): void {
		$this->insertPeer(self::HASH, '__TEST_PSC_FRESH__', 1, self::$time);
		$this->insertPeer(self::HASH, '__TEST_PSC_STALE__', 1, self::$time - 10000);

		$counts = peers_count_swarm(self::$connection, self::$settings, self::HASH, self::$time - 100);
		$this->assertSame(1, $counts['complete']);
	}

	public function testExcludesPeersFromOtherTorrents(): void {
		$this->insertPeer(self::HASH,       '__TEST_PSC_MINE__',  1, self::$time);
		$this->insertPeer(self::OTHER_HASH, '__TEST_PSC_OTHER__', 1, self::$time);

		$counts = peers_count_swarm(self::$connection, self::$settings, self::HASH, self::$time - 100);
		$this->assertSame(1, $counts['complete']);
		$this->assertSame(0, $counts['incomplete']);
	}

}
