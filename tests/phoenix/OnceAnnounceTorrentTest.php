<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class OnceAnnounceTorrentTest extends PhoenixTestCase {

	private const HASH = '__TEST_OAT_HASH__';
	private const SELF = '__TEST_OAT_SELF__';

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
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function basePeer(array $overrides = array()): array {
		return array_merge(array(
			'info_hash'  => self::HASH,
			'peer_id'    => self::SELF,
			'numwant'    => 50,
			'compact'    => 0,
			'no_peer_id' => 0,
			'state'      => 0,
			'left'       => 100,
			'uploaded'   => 0,
			'downloaded' => 0,
			'ipv4'       => '',
			'ipv6'       => '',
			'port'       => '',
			'portv4'     => '0',
			'portv6'     => '0',
		), $overrides);
	}

	private function insertOtherPeer(string $peer_id, int $state, string $ipv4, int $portv4): void {
		$row = array(
			'info_hash'  => self::HASH,
			'peer_id'    => $peer_id,
			'state'      => $state,
			'left'       => 0,
			'uploaded'   => 0,
			'downloaded' => 0,
			'ipv4'       => $ipv4,
			'ipv6'       => '',
			'port'       => $portv4,
			'portv4'     => $portv4,
			'portv6'     => '0',
		);
		peer_new(self::$connection, self::$settings, self::$time, $row);
	}

	/** @param array<string, mixed> $peer */
	private function runOnce(array &$peer): string {
		$connection = self::$connection;
		$settings   = self::$settings;
		$time       = self::$time;
		ob_start();
		require $settings['onces'].'once.announce.torrent.php';
		return ob_get_clean();
	}

	public function testEmptySwarmReturnsZeroCounts(): void {
		$peer = $this->basePeer(array('compact' => 1));
		$output = $this->runOnce($peer);

		$this->assertStringStartsWith('d8:completei0e10:incompletei0e', $output);
	}

	public function testIntervalsComeFromSettings(): void {
		$peer = $this->basePeer(array('compact' => 1));
		$output = $this->runOnce($peer);

		$this->assertStringContainsString('8:intervali'.self::$settings['announce_interval'].'e', $output);
		$this->assertStringContainsString('12:min intervali'.self::$settings['min_interval'].'e', $output);
	}

	public function testCompactResponseIncludesPeerBytesForOtherPeer(): void {
		$this->insertOtherPeer('__TEST_OAT_OTHER__', 1, '192.0.2.5', 9999);

		$peer = $this->basePeer(array('compact' => 1));
		$output = $this->runOnce($peer);

		// Compact IPv4 = 4-byte IP + 2-byte port (BEP 23).
		$expected = pack('Nn', ip2long('192.0.2.5'), 9999);
		$this->assertStringContainsString($expected, $output);
		// Counts should reflect the inserted seeder.
		$this->assertStringContainsString('d8:completei1e10:incompletei0e', $output);
	}

	public function testNonCompactResponseIncludesBencodeList(): void {
		$this->insertOtherPeer('__TEST_OAT_OTHER__', 1, '192.0.2.5', 9999);

		$peer = $this->basePeer(array('compact' => 0));
		$output = $this->runOnce($peer);

		// Non-compact peers section is a bencode list 'l...e' inside the dict.
		$this->assertStringContainsString('5:peersl', $output);
		// peer_format_bencode emits '4:porti9999e' for the peer's IPv4 port.
		$this->assertStringContainsString('4:porti9999e', $output);
	}

	public function testExcludesAnnouncerFromPeerList(): void {
		// Insert a peer with the same peer_id as the announcer; it must not appear.
		$this->insertOtherPeer(self::SELF, 0, '192.0.2.99', 7777);

		$peer = $this->basePeer(array('compact' => 1));
		$output = $this->runOnce($peer);

		$selfBytes = pack('Nn', ip2long('192.0.2.99'), 7777);
		$this->assertStringNotContainsString($selfBytes, $output);
	}

}
