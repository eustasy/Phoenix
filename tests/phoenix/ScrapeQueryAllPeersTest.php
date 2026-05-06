<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ScrapeQueryAllPeersTest extends PhoenixTestCase {

	public function testQueryAllPeersReturnsAllTorrents() {
		require_once __DIR__.'/../../src/model/peers.scrape.all.php';

		$info_hash_a = str_repeat('a', 40);
		$info_hash_b = str_repeat('b', 40);
		$peer_id_1 = str_repeat('1', 40);
		$peer_id_2 = str_repeat('2', 40);
		$peer_id_3 = str_repeat('3', 40);
		$peer_id_4 = str_repeat('4', 40);

		// Insert test peers for multiple torrents
		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			   '(`info_hash`, `peer_id`, `state`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `updated`) VALUES '.
			   "('".$info_hash_a."', '".$peer_id_1."', '1', '', 'fc00::1', '', '', 0, 6881, ".self::$time."), ".
			   "('".$info_hash_a."', '".$peer_id_2."', '0', '', 'fc00::2', '', '', 0, 6881, ".self::$time."), ".
			   "('".$info_hash_b."', '".$peer_id_3."', '1', '', 'fc00::3', '', '', 0, 6881, ".self::$time."), ".
			   "('".$info_hash_b."', '".$peer_id_4."', '1', '', 'fc00::4', '', '', 0, 6881, ".self::$time.");";
		mysqli_query(self::$connection, $sql);

		$result = peers_scrape_all(self::$connection, self::$settings);

		$this->assertNotFalse($result);
		
		$rows = array();
		while ($row = mysqli_fetch_assoc($result)) {
			$rows[$row['info_hash']] = $row;
		}

		$this->assertCount(2, $rows);
		$this->assertArrayHasKey($info_hash_a, $rows);
		$this->assertArrayHasKey($info_hash_b, $rows);
		
		// Torrent A: 1 seeder, 1 leecher
		$this->assertSame('1', $rows[$info_hash_a]['seeders']);
		$this->assertSame('1', $rows[$info_hash_a]['leechers']);
		
		// Torrent B: 2 seeders, 0 leechers
		$this->assertSame('2', $rows[$info_hash_b]['seeders']);
		$this->assertSame('0', $rows[$info_hash_b]['leechers']);
	}

	public function testQueryAllPeersReturnsEmptyWhenNoPeers() {
		require_once __DIR__.'/../../src/model/peers.scrape.all.php';

		$result = peers_scrape_all(self::$connection, self::$settings);

		$this->assertNotFalse($result);
		$this->assertSame(0, mysqli_num_rows($result));
	}

	public function testQueryAllPeersGroupsByInfoHash() {
		require_once __DIR__.'/../../src/model/peers.scrape.all.php';

		$info_hash = str_repeat('c', 40);
		$peer_id_1 = str_repeat('5', 40);
		$peer_id_2 = str_repeat('6', 40);
		$peer_id_3 = str_repeat('7', 40);

		// Insert multiple peers for same torrent
		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			   '(`info_hash`, `peer_id`, `state`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `updated`) VALUES '.
			   "('".$info_hash."', '".$peer_id_1."', '1', '', 'fc00::5', '', '', 0, 6881, ".self::$time."), ".
			   "('".$info_hash."', '".$peer_id_2."', '1', '', 'fc00::6', '', '', 0, 6881, ".self::$time."), ".
			   "('".$info_hash."', '".$peer_id_3."', '0', '', 'fc00::7', '', '', 0, 6881, ".self::$time.");";
		mysqli_query(self::$connection, $sql);

		$result = peers_scrape_all(self::$connection, self::$settings);

		$this->assertNotFalse($result);
		$this->assertSame(1, mysqli_num_rows($result)); // Only one row despite 3 peers
		
		$row = mysqli_fetch_assoc($result);
		$this->assertSame($info_hash, $row['info_hash']);
		$this->assertSame('2', $row['seeders']);
		$this->assertSame('1', $row['leechers']);
	}

	protected function tearDown(): void {
		mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `peer_id` LIKE \'__TEST_%\' OR `peer_id` REGEXP \'^[0-9]+$\'');
	}

}
