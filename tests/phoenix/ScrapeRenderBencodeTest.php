<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ScrapeRenderBencodeTest extends PhoenixTestCase {

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
				'peers'     => 3,
				'size'      => 1024,
				'downloads' => 7,
				'traffic'   => 7168,
			),
		);
	}

	public function testStartsWithFilesDictKey(): void {
		$out = view_scrape_bencode($this->fixture());
		$this->assertStringStartsWith('d5:files', $out);
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
		$expected = 'd5:files'.
			'd20:'.$rawHash.
				'd8:completei2e10:downloadedi7e10:incompletei1ee'.
			'ee';
		$this->assertSame($expected, $out);
	}

	public function testEmptyScrapeStillBalancesContainer(): void {
		// Boundary: with no torrents, the files dict is empty.
		// Correct bencode: outer dict with "files" key pointing to empty dict.
		$out = view_scrape_bencode(array());
		$this->assertSame('d5:filesdee', $out);
	}

}
