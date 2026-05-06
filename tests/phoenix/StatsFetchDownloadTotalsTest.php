<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class StatsFetchDownloadTotalsTest extends PhoenixTestCase {

	public function testFetchDownloadTotals() {
		require_once __DIR__.'/../../src/model/stats.downloads.php';

		// Insert test torrents
		$info_hash_a = str_repeat('a', 40);
		$info_hash_b = str_repeat('b', 40);

		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
			   '(`info_hash`, `name`, `size`, `downloads`) VALUES '.
			   "('".$info_hash_a."', '__TEST_a', 1000, 5), ".
			   "('".$info_hash_b."', '__TEST_b', 2000, 3);";
		mysqli_query(self::$connection, $sql);

		$result = stats_fetch_download_totals(self::$connection, self::$settings);

		$this->assertIsArray($result);
		$this->assertEquals('8', $result['downloads']); // 5 + 3
		$this->assertEquals('11000', $result['traffic']); // 5*1000 + 3*2000
	}

	public function testFetchDownloadTotalsEmpty() {
		require_once __DIR__.'/../../src/model/stats.downloads.php';

		$result = stats_fetch_download_totals(self::$connection, self::$settings);

		$this->assertIsArray($result);
		$this->assertNull($result['downloads']);
		$this->assertNull($result['traffic']);
	}

	public function testFetchDownloadTotalsWithNullSize() {
		require_once __DIR__.'/../../src/model/stats.downloads.php';

		// Insert torrent with NULL size
		$info_hash = str_repeat('c', 40);
		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
			   '(`info_hash`, `name`, `size`, `downloads`) VALUES '.
			   "('".$info_hash."', '__TEST_c', NULL, 10);";
		mysqli_query(self::$connection, $sql);

		$result = stats_fetch_download_totals(self::$connection, self::$settings);

		$this->assertIsArray($result);
		$this->assertEquals('10', $result['downloads']);
		$this->assertEquals('0', $result['traffic']); // IFNULL handles NULL size
	}

	protected function tearDown(): void {
		mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `name` LIKE \'__TEST_%\'');
	}

}
