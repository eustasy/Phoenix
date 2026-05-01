<?php

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class StatsFetchPeerCountsTest extends PhoenixTestCase {

	public function testFetchPeerCounts() {
		require_once self::$settings['functions'].'function.stats.fetch.peer.counts.php';

		// Insert test peers
		$info_hash_a = str_repeat('a', 40);
		$info_hash_b = str_repeat('b', 40);
		$peer_id_1 = str_repeat('1', 40);
		$peer_id_2 = str_repeat('2', 40);
		$peer_id_3 = str_repeat('3', 40);

		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			   '(`info_hash`, `peer_id`, `state`, `ip`, `port`, `updated`) VALUES '.
			   "('".$info_hash_a."', '".$peer_id_1."', '1', 'fc00::1', 6881, ".self::$time."), ".
			   "('".$info_hash_a."', '".$peer_id_2."', '0', 'fc00::2', 6881, ".self::$time."), ".
			   "('".$info_hash_b."', '".$peer_id_3."', '1', 'fc00::3', 6881, ".self::$time.");";
		mysqli_query(self::$connection, $sql);

		$result = stats_fetch_peer_counts(self::$connection, self::$settings);

		$this->assertIsArray($result);
		$this->assertEquals('2', $result['seeders']);
		$this->assertEquals('1', $result['leechers']);
		$this->assertEquals('2', $result['torrents']);
	}

	public function testFetchPeerCountsEmpty() {
		require_once self::$settings['functions'].'function.stats.fetch.peer.counts.php';

		$result = stats_fetch_peer_counts(self::$connection, self::$settings);

		$this->assertIsArray($result);
		$this->assertEquals('0', $result['seeders']);
		$this->assertEquals('0', $result['leechers']);
		$this->assertEquals('0', $result['torrents']);
	}

	protected function tearDown(): void {
		mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `peer_id` LIKE \'__TEST_%\'');
	}

}
