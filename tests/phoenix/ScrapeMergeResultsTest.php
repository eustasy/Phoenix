<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use mysqli_result;

class ScrapeMergeResultsTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/functions/scrape.merge.results.php';
	}

	private function syntheticPeers(): mysqli_result {
		$result = mysqli_query(self::$connection,
			"SELECT 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' AS `info_hash`, 2 AS `seeders`, 1 AS `leechers` ".
			"UNION ALL SELECT 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 0, 5"
		);
		$this->assertInstanceOf(mysqli_result::class, $result);
		return $result;
	}

	private function syntheticTorrents(): mysqli_result {
		$result = mysqli_query(self::$connection,
			"SELECT 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' AS `info_hash`, 1024 AS `size`, 7 AS `downloads` ".
			"UNION ALL SELECT 'cccccccccccccccccccccccccccccccccccccccc', 2048, 3"
		);
		$this->assertInstanceOf(mysqli_result::class, $result);
		return $result;
	}

	private function emptyResult(): mysqli_result {
		$result = mysqli_query(self::$connection,
			"SELECT '' AS `info_hash`, 0 AS `seeders`, 0 AS `leechers`, 0 AS `size`, 0 AS `downloads` WHERE 0"
		);
		$this->assertInstanceOf(mysqli_result::class, $result);
		return $result;
	}

	public function testEmptyInputsReturnEmptyArray(): void {
		$out = scrape_merge_results($this->emptyResult(), $this->emptyResult());
		$this->assertSame(array(), $out);
	}

	public function testMergesPeerCountsAndTorrentMeta(): void {
		$out = scrape_merge_results($this->syntheticPeers(), $this->syntheticTorrents());
		$hashA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$this->assertArrayHasKey($hashA, $out);
		$this->assertSame($hashA, $out[$hashA]['info_hash']);
		$this->assertSame(2,    $out[$hashA]['seeders']);
		$this->assertSame(1,    $out[$hashA]['leechers']);
		$this->assertSame(3,    $out[$hashA]['peers']);
		$this->assertSame(1024, $out[$hashA]['size']);
		$this->assertSame(7,    $out[$hashA]['downloads']);
		$this->assertSame(7168, $out[$hashA]['traffic']);
	}

	public function testTorrentWithoutPeersHasZeroCounts(): void {
		// Hash C exists in torrents but not peers.
		$out = scrape_merge_results($this->syntheticPeers(), $this->syntheticTorrents());
		$hashC = 'cccccccccccccccccccccccccccccccccccccccc';
		$this->assertArrayHasKey($hashC, $out);
		$this->assertSame(0,    $out[$hashC]['seeders']);
		$this->assertSame(0,    $out[$hashC]['leechers']);
		$this->assertSame(0,    $out[$hashC]['peers']);
		$this->assertSame(2048, $out[$hashC]['size']);
		$this->assertSame(3,    $out[$hashC]['downloads']);
		$this->assertSame(6144, $out[$hashC]['traffic']);
	}

	public function testPeersWithoutTorrentRowHasZeroSizeAndDownloads(): void {
		// Hash B exists in peers but not torrents.
		$out = scrape_merge_results($this->syntheticPeers(), $this->syntheticTorrents());
		$hashB = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
		$this->assertArrayHasKey($hashB, $out);
		$this->assertSame(0, $out[$hashB]['seeders']);
		$this->assertSame(5, $out[$hashB]['leechers']);
		$this->assertSame(5, $out[$hashB]['peers']);
		$this->assertSame(0, $out[$hashB]['size']);
		$this->assertSame(0, $out[$hashB]['downloads']);
		$this->assertSame(0, $out[$hashB]['traffic']);
	}

	public function testPreInitialisedScrapeEntriesArePreserved(): void {
		// once.scrape.torrent.php seeds $scrape with zeros for requested-but-unknown
		// hashes; those entries must survive the merge as all-zero records.
		$hashD = 'dddddddddddddddddddddddddddddddddddddddd';
		$pre = array($hashD => array(
			'info_hash' => $hashD,
			'seeders'   => 0,
			'leechers'  => 0,
			'downloads' => 0,
			'peers'     => 0,
			'size'      => 0,
			'traffic'   => 0,
		));
		$out = scrape_merge_results($this->emptyResult(), $this->emptyResult(), $pre);
		$this->assertArrayHasKey($hashD, $out);
		$this->assertSame($hashD, $out[$hashD]['info_hash']);
		$this->assertSame(0, $out[$hashD]['seeders']);
		$this->assertSame(0, $out[$hashD]['leechers']);
		$this->assertSame(0, $out[$hashD]['peers']);
		$this->assertSame(0, $out[$hashD]['size']);
		$this->assertSame(0, $out[$hashD]['downloads']);
		$this->assertSame(0, $out[$hashD]['traffic']);
	}

	public function testIntvalCoercesStringNumericsFromMysqli(): void {
		// mysqli returns column values as strings by default; verify they're coerced.
		$out = scrape_merge_results($this->syntheticPeers(), $this->syntheticTorrents());
		$hashA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$this->assertIsInt($out[$hashA]['seeders']);
		$this->assertIsInt($out[$hashA]['leechers']);
		$this->assertIsInt($out[$hashA]['size']);
		$this->assertIsInt($out[$hashA]['downloads']);
		$this->assertIsInt($out[$hashA]['peers']);
		$this->assertIsInt($out[$hashA]['traffic']);
	}

}
