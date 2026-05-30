<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class TorrentsScrapeAllTest extends PhoenixTestCase {

	public function testQueryAllTorrentsReturnsAllTorrents() {
		require_once __DIR__.'/../../src/model/torrents.scrape.all.php';

		$info_hash_a = str_repeat('a', 40);
		$info_hash_b = str_repeat('b', 40);
		$info_hash_c = str_repeat('c', 40);

		// Insert test torrents
		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
			   '(`info_hash`, `name`, `size`, `downloads`) VALUES '.
			   "('".$info_hash_a."', '__TEST_a', 1000, 5), ".
			   "('".$info_hash_b."', '__TEST_b', 2000, 3), ".
			   "('".$info_hash_c."', '__TEST_c', 3000, 10);";
		mysqli_query(self::$connection, $sql);

		$result = torrents_scrape_all(self::$connection, self::$settings);

		$this->assertNotFalse($result);
		
		$rows = array();
		while ($row = mysqli_fetch_assoc($result)) {
			$rows[$row['info_hash']] = $row;
		}

		$this->assertCount(3, $rows);
		$this->assertArrayHasKey($info_hash_a, $rows);
		$this->assertArrayHasKey($info_hash_b, $rows);
		$this->assertArrayHasKey($info_hash_c, $rows);
		
		$this->assertSame('5', $rows[$info_hash_a]['downloads']);
		$this->assertSame('3', $rows[$info_hash_b]['downloads']);
		$this->assertSame('10', $rows[$info_hash_c]['downloads']);
	}

	public function testQueryAllTorrentsReturnsEmptyWhenNoTorrents() {
		require_once __DIR__.'/../../src/model/torrents.scrape.all.php';

		$result = torrents_scrape_all(self::$connection, self::$settings);

		$this->assertNotFalse($result);
		$this->assertSame(0, mysqli_num_rows($result));
	}

	public function testQueryAllTorrentsIncludesInfoHashSizeAndDownloads() {
		require_once __DIR__.'/../../src/model/torrents.scrape.all.php';

		$info_hash = str_repeat('d', 40);

		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
			   '(`info_hash`, `name`, `size`, `downloads`) VALUES '.
			   "('".$info_hash."', '__TEST_d', 5000, 42);";
		mysqli_query(self::$connection, $sql);

		$result = torrents_scrape_all(self::$connection, self::$settings);

		$this->assertNotFalse($result);
		$row = mysqli_fetch_assoc($result);

		$this->assertArrayHasKey('info_hash', $row);
		$this->assertArrayHasKey('size',      $row);
		$this->assertArrayHasKey('downloads', $row);
		$this->assertSame($info_hash, $row['info_hash']);
		$this->assertSame('5000',      $row['size']);
		$this->assertSame('42',        $row['downloads']);
	}

	protected function tearDown(): void {
		mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `name` LIKE \'__TEST_%\'');
	}

}
