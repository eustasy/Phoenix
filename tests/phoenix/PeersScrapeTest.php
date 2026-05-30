<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class PeersScrapeTest extends PhoenixTestCase {

	public function testQueryPeersWithSingleHash() {
		require_once __DIR__.'/../../src/model/peers.scrape.php';
		require_once __DIR__.'/../../src/functions/scrape.build.where.clause.php';

		$info_hash = str_repeat('a', 40);
		$peer_id_1 = str_repeat('1', 40);
		$peer_id_2 = str_repeat('2', 40);

		// Insert test peers
		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			   '(`info_hash`, `peer_id`, `state`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `updated`) VALUES '.
			   "('".$info_hash."', '".$peer_id_1."', '1', '', 'fc00::1', '', '', 0, 6881, ".self::$time."), ".
			   "('".$info_hash."', '".$peer_id_2."', '0', '', 'fc00::2', '', '', 0, 6881, ".self::$time.");";
		mysqli_query(self::$connection, $sql);

		$where = scrape_build_where_clause(array($info_hash));
		$result = peers_scrape(self::$connection, self::$settings, $where);

		$this->assertNotFalse($result);
		$row = mysqli_fetch_assoc($result);
		$this->assertSame($info_hash, $row['info_hash']);
		$this->assertSame('1', $row['seeders']);
		$this->assertSame('1', $row['leechers']);
	}

	public function testQueryPeersWithMultipleHashes() {
		require_once __DIR__.'/../../src/model/peers.scrape.php';
		require_once __DIR__.'/../../src/functions/scrape.build.where.clause.php';

		$info_hash_a = str_repeat('a', 40);
		$info_hash_b = str_repeat('b', 40);
		$peer_id_1 = str_repeat('1', 40);
		$peer_id_2 = str_repeat('2', 40);
		$peer_id_3 = str_repeat('3', 40);

		// Insert test peers for two torrents
		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			   '(`info_hash`, `peer_id`, `state`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `updated`) VALUES '.
			   "('".$info_hash_a."', '".$peer_id_1."', '1', '', 'fc00::1', '', '', 0, 6881, ".self::$time."), ".
			   "('".$info_hash_a."', '".$peer_id_2."', '0', '', 'fc00::2', '', '', 0, 6881, ".self::$time."), ".
			   "('".$info_hash_b."', '".$peer_id_3."', '1', '', 'fc00::3', '', '', 0, 6881, ".self::$time.");";
		mysqli_query(self::$connection, $sql);

		$where = scrape_build_where_clause(array($info_hash_a, $info_hash_b));
		$result = peers_scrape(self::$connection, self::$settings, $where);

		$this->assertNotFalse($result);
		
		$rows = array();
		while ($row = mysqli_fetch_assoc($result)) {
			$rows[$row['info_hash']] = $row;
		}

		$this->assertCount(2, $rows);
		$this->assertArrayHasKey($info_hash_a, $rows);
		$this->assertArrayHasKey($info_hash_b, $rows);
		$this->assertSame('1', $rows[$info_hash_a]['seeders']);
		$this->assertSame('1', $rows[$info_hash_a]['leechers']);
		$this->assertSame('1', $rows[$info_hash_b]['seeders']);
		$this->assertSame('0', $rows[$info_hash_b]['leechers']);
	}

	public function testQueryPeersReturnsEmptyForUnknownHash() {
		require_once __DIR__.'/../../src/model/peers.scrape.php';
		require_once __DIR__.'/../../src/functions/scrape.build.where.clause.php';

		$info_hash = str_repeat('z', 40);
		$where = scrape_build_where_clause(array($info_hash));
		$result = peers_scrape(self::$connection, self::$settings, $where);

		$this->assertNotFalse($result);
		$this->assertSame(0, mysqli_num_rows($result));
	}

	protected function tearDown(): void {
		mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `peer_id` LIKE \'__TEST_%\' OR `peer_id` REGEXP \'^[0-9]+$\'');
	}

}
