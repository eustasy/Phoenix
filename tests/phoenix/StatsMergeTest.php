<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class StatsMergeTest extends TestCase {

	public function testMergeStats() {
		require_once __DIR__.'/../../src/functions/stats.merge.php';

		$peer_counts = array(
			'seeders' => '10',
			'leechers' => '5',
			'torrents' => '3',
		);
		$download_totals = array(
			'downloads' => '100',
			'traffic' => '5000000',
		);

		$result = stats_merge($peer_counts, $download_totals);

		$this->assertIsArray($result);
		$this->assertSame(10, $result['seeders']);
		$this->assertSame(5, $result['leechers']);
		$this->assertSame(3, $result['torrents']);
		$this->assertSame(100, $result['downloads']);
		$this->assertSame(5000000, $result['traffic']);
		$this->assertSame(15, $result['peers']); // seeders + leechers
	}

	public function testMergeStatsWithZeros() {
		require_once __DIR__.'/../../src/functions/stats.merge.php';

		$peer_counts = array(
			'seeders' => '0',
			'leechers' => '0',
			'torrents' => '0',
		);
		$download_totals = array(
			'downloads' => '0',
			'traffic' => '0',
		);

		$result = stats_merge($peer_counts, $download_totals);

		$this->assertIsArray($result);
		$this->assertSame(0, $result['seeders']);
		$this->assertSame(0, $result['leechers']);
		$this->assertSame(0, $result['torrents']);
		$this->assertSame(0, $result['downloads']);
		$this->assertSame(0, $result['traffic']);
		$this->assertSame(0, $result['peers']);
	}

	public function testMergeStatsWithNullValues() {
		require_once __DIR__.'/../../src/functions/stats.merge.php';

		$peer_counts = array(
			'seeders' => null,
			'leechers' => null,
			'torrents' => null,
		);
		$download_totals = array(
			'downloads' => null,
			'traffic' => null,
		);

		$result = stats_merge($peer_counts, $download_totals);

		$this->assertIsArray($result);
		$this->assertSame(0, $result['seeders']);
		$this->assertSame(0, $result['leechers']);
		$this->assertSame(0, $result['torrents']);
		$this->assertSame(0, $result['downloads']);
		$this->assertSame(0, $result['traffic']);
		$this->assertSame(0, $result['peers']);
	}

	public function testMergeStatsReturnsFalseWhenPeerCountsIsFalse() {
		require_once __DIR__.'/../../src/functions/stats.merge.php';

		$download_totals = array(
			'downloads' => '100',
			'traffic' => '5000000',
		);

		$result = stats_merge(false, $download_totals);

		$this->assertFalse($result);
	}

	public function testMergeStatsReturnsFalseWhenDownloadTotalsIsFalse() {
		require_once __DIR__.'/../../src/functions/stats.merge.php';

		$peer_counts = array(
			'seeders' => '10',
			'leechers' => '5',
			'torrents' => '3',
		);

		$result = stats_merge($peer_counts, false);

		$this->assertFalse($result);
	}

	public function testMergeStatsReturnsFalseWhenBothAreFalse() {
		require_once __DIR__.'/../../src/functions/stats.merge.php';

		$result = stats_merge(false, false);

		$this->assertFalse($result);
	}

}
