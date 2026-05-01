<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewScrapeBencodeTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['views'].'bencode.scrape.php';
	}

	/** @return array<string, array<string, int|string>> */
	private function fixture(): array {
		return array(
			'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' => array(
				'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
				'seeders'   => 2,
				'leechers'  => 1,
				'downloads' => 7,
			),
		);
	}

	public function testStartsWithFilesDictKey(): void {
		$out = view_scrape_bencode($this->fixture());
		$this->assertStringStartsWith('d5:filesd', $out);
	}

	public function testInfoHashEncodedAsRawTwentyBytes(): void {
		$out = view_scrape_bencode($this->fixture());
		// BEP 15: key is the raw 20-byte info_hash, prefixed by '20:'.
		$rawHash = hex2bin('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
		$this->assertStringContainsString('20:'.$rawHash, $out);
	}

	public function testStatsDictUsesBepKeysAndCounts(): void {
		$out = view_scrape_bencode($this->fixture());
		$this->assertStringContainsString('8:completei2e',     $out);
		$this->assertStringContainsString('10:downloadedi7e',  $out);
		$this->assertStringContainsString('10:incompletei1e',  $out);
	}

	public function testSingleTorrentMatchesExactBencode(): void {
		$out = view_scrape_bencode($this->fixture());
		$rawHash  = hex2bin('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
		$expected = 'd5:filesd'.
			'20:'.$rawHash.
				'd8:completei2e10:downloadedi7e10:incompletei1ee'.
			'ee';
		$this->assertSame($expected, $out);
	}

	public function testEmptyScrapeStillBalancesContainer(): void {
		// Boundary: with no torrents, the files dict is empty.
		// Correct bencode: outer dict with "files" key pointing to empty dict.
		// d 5:files d e e
		//   ^outer  ^files dict  ^close files  ^close outer
		$out = view_scrape_bencode(array());
		$expected = 'd' . '5:files' . 'd' . 'e' . 'e';
		$this->assertSame($expected, $out);
	}

	public function testMultipleTorrents(): void {
		$scrape = array(
			'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' => array(
				'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
				'seeders'   => 2,
				'leechers'  => 1,
				'downloads' => 7,
			),
			'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' => array(
				'info_hash' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
				'seeders'   => 5,
				'leechers'  => 3,
				'downloads' => 12,
			),
		);

		$out = view_scrape_bencode($scrape);

		// Should contain both info_hashes
		$this->assertStringContainsString(hex2bin('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'), $out);
		$this->assertStringContainsString(hex2bin('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'), $out);
		
		// Should contain both sets of stats
		$this->assertStringContainsString('8:completei2e', $out);
		$this->assertStringContainsString('8:completei5e', $out);
		$this->assertStringContainsString('10:incompletei1e', $out);
		$this->assertStringContainsString('10:incompletei3e', $out);
	}

	public function testZeroValues(): void {
		$scrape = array(
			'cccccccccccccccccccccccccccccccccccccccc' => array(
				'info_hash' => 'cccccccccccccccccccccccccccccccccccccccc',
				'seeders'   => 0,
				'leechers'  => 0,
				'downloads' => 0,
			),
		);

		$out = view_scrape_bencode($scrape);
		
		$this->assertStringContainsString('8:completei0e', $out);
		$this->assertStringContainsString('10:incompletei0e', $out);
		$this->assertStringContainsString('10:downloadedi0e', $out);
	}

	public function testLargeValues(): void {
		$scrape = array(
			'dddddddddddddddddddddddddddddddddddddddd' => array(
				'info_hash' => 'dddddddddddddddddddddddddddddddddddddddd',
				'seeders'   => 999999,
				'leechers'  => 888888,
				'downloads' => 777777,
			),
		);

		$out = view_scrape_bencode($scrape);
		
		$this->assertStringContainsString('8:completei999999e', $out);
		$this->assertStringContainsString('10:incompletei888888e', $out);
		$this->assertStringContainsString('10:downloadedi777777e', $out);
	}

}
