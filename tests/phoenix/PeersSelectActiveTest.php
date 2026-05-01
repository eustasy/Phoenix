<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeersSelectActiveTest extends PhoenixTestCase {

	private const HASH = '__TEST_PSA_HASH__';
	private const SELF = '__TEST_PSA_SELF__';

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.peers.select.active.php';
		require_once self::$settings['model'].'peer.insert.php';
	}

	protected function tearDown(): void {
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
	}

	private function insertPeer(string $info_hash, string $peer_id, int $state, int $time): void {
		$row = array(
			'info_hash'  => $info_hash,
			'peer_id'    => $peer_id,
			'state'      => $state,
			'left'       => 0,
			'uploaded'   => 0,
			'downloaded' => 0,
			'ipv4'       => '',
			'ipv6'       => '',
			'port'       => '',
			'portv4'     => '0',
			'portv6'     => '0',
		);
			peer_insert(self::$connection, self::$settings, $time, $row);
	}

	/** @return array<string, mixed> */
	private function announcer(int $numwant = 50): array {
		return array(
			'info_hash' => self::HASH,
			'peer_id'   => self::SELF,
			'numwant'   => $numwant,
		);
	}

	/** @return array{where: string, order: string} */
	private function emptyStrategy(): array {
		return array('where' => '', 'order' => '');
	}

	public function testEmptySwarmReturnsEmptyArray(): void {
		$rows = peers_select_active(
			self::$connection, self::$settings,
			$this->announcer(), self::$time - 100, $this->emptyStrategy()
		);
		$this->assertSame(array(), $rows);
	}

	public function testReturnsOtherPeersForSameTorrent(): void {
		$this->insertPeer(self::HASH, '__TEST_PSA_OTHER__', 0, self::$time);

		$rows = peers_select_active(
			self::$connection, self::$settings,
			$this->announcer(), self::$time - 100, $this->emptyStrategy()
		);
		$this->assertCount(1, $rows);
		$this->assertSame('__TEST_PSA_OTHER__', $rows[0]['peer_id']);
	}

	public function testExcludesAnnouncerByPeerId(): void {
		$this->insertPeer(self::HASH, self::SELF,             0, self::$time);
		$this->insertPeer(self::HASH, '__TEST_PSA_OTHER__',   0, self::$time);

		$rows = peers_select_active(
			self::$connection, self::$settings,
			$this->announcer(), self::$time - 100, $this->emptyStrategy()
		);
		$this->assertCount(1, $rows);
		$this->assertSame('__TEST_PSA_OTHER__', $rows[0]['peer_id']);
	}

	public function testExcludesPeersFromOtherTorrents(): void {
		$this->insertPeer(self::HASH,             '__TEST_PSA_MINE__',  0, self::$time);
		$this->insertPeer('__TEST_PSA_OTHER_T__', '__TEST_PSA_OTHER__', 0, self::$time);

		$rows = peers_select_active(
			self::$connection, self::$settings,
			$this->announcer(), self::$time - 100, $this->emptyStrategy()
		);
		$this->assertCount(1, $rows);
		$this->assertSame('__TEST_PSA_MINE__', $rows[0]['peer_id']);
	}

	public function testExcludesStalePeers(): void {
		$this->insertPeer(self::HASH, '__TEST_PSA_FRESH__', 0, self::$time);
		$this->insertPeer(self::HASH, '__TEST_PSA_STALE__', 0, self::$time - 10000);

		$rows = peers_select_active(
			self::$connection, self::$settings,
			$this->announcer(), self::$time - 100, $this->emptyStrategy()
		);
		$this->assertCount(1, $rows);
		$this->assertSame('__TEST_PSA_FRESH__', $rows[0]['peer_id']);
	}

	public function testRespectsNumwantLimit(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->insertPeer(self::HASH, '__TEST_PSA_P'.$i.'__', 0, self::$time);
		}

		$rows = peers_select_active(
			self::$connection, self::$settings,
			$this->announcer(2), self::$time - 100, $this->emptyStrategy()
		);
		$this->assertCount(2, $rows);
	}

}
