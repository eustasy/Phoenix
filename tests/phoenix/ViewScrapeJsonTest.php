<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewScrapeJsonTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['views'].'json.scrape.php';
	}

	public function testReturnsValidJson() {
		$scrape = array(
			array(
				'info_hash' => 'abcdef1234567890abcdef1234567890abcdef12',
				'seeders'   => 10,
				'leechers'  => 5,
				'peers'     => 15,
				'size'      => 1073741824,
				'downloads' => 25,
				'traffic'   => 26843545600,
			),
		);

		$result = view_scrape_json($scrape);

		$this->assertJson($result, 'Output should be valid JSON');
	}

	public function testSingleTorrentIndexedByInfoHash() {
		$info_hash = 'abcdef1234567890abcdef1234567890abcdef12';
		$scrape = array(
			array(
				'info_hash' => $info_hash,
				'seeders'   => 10,
				'leechers'  => 5,
				'peers'     => 15,
				'size'      => 1073741824,
				'downloads' => 25,
				'traffic'   => 26843545600,
			),
		);

		$result = view_scrape_json($scrape);
		$decoded = json_decode($result, true);

		$this->assertArrayHasKey($info_hash, $decoded);
		$this->assertIsArray($decoded[$info_hash]);
	}

	public function testIncludesAllTorrentFields() {
		$scrape = array(
			array(
				'info_hash' => 'abcdef1234567890abcdef1234567890abcdef12',
				'seeders'   => 10,
				'leechers'  => 5,
				'peers'     => 15,
				'size'      => 1073741824,
				'downloads' => 25,
				'traffic'   => 26843545600,
			),
		);

		$result = view_scrape_json($scrape);
		$decoded = json_decode($result, true);
		$torrent = reset($decoded);

		$this->assertArrayHasKey('info_hash', $torrent);
		$this->assertArrayHasKey('seeders', $torrent);
		$this->assertArrayHasKey('leechers', $torrent);
		$this->assertArrayHasKey('peers', $torrent);
		$this->assertArrayHasKey('size', $torrent);
		$this->assertArrayHasKey('downloads', $torrent);
		$this->assertArrayHasKey('traffic', $torrent);
	}

	public function testCorrectTorrentValues() {
		$scrape = array(
			array(
				'info_hash' => 'abcdef1234567890abcdef1234567890abcdef12',
				'seeders'   => 10,
				'leechers'  => 5,
				'peers'     => 15,
				'size'      => 1073741824,
				'downloads' => 25,
				'traffic'   => 26843545600,
			),
		);

		$result = view_scrape_json($scrape);
		$decoded = json_decode($result, true);
		$torrent = reset($decoded);

		$this->assertEquals('abcdef1234567890abcdef1234567890abcdef12', $torrent['info_hash']);
		$this->assertEquals(10, $torrent['seeders']);
		$this->assertEquals(5, $torrent['leechers']);
		$this->assertEquals(15, $torrent['peers']);
		$this->assertEquals(1073741824, $torrent['size']);
		$this->assertEquals(25, $torrent['downloads']);
		$this->assertEquals(26843545600, $torrent['traffic']);
	}

	public function testMultipleTorrents() {
		$hash1 = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$hash2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

		$scrape = array(
			array(
				'info_hash' => $hash1,
				'seeders'   => 10,
				'leechers'  => 5,
				'peers'     => 15,
				'size'      => 1073741824,
				'downloads' => 25,
				'traffic'   => 26843545600,
			),
			array(
				'info_hash' => $hash2,
				'seeders'   => 3,
				'leechers'  => 2,
				'peers'     => 5,
				'size'      => 536870912,
				'downloads' => 10,
				'traffic'   => 5368709120,
			),
		);

		$result = view_scrape_json($scrape);
		$decoded = json_decode($result, true);

		$this->assertCount(2, $decoded);
		$this->assertArrayHasKey($hash1, $decoded);
		$this->assertArrayHasKey($hash2, $decoded);
		$this->assertEquals(10, $decoded[$hash1]['seeders']);
		$this->assertEquals(3, $decoded[$hash2]['seeders']);
	}

	public function testEmptyScrape() {
		$scrape = array();

		$result = view_scrape_json($scrape);
		$decoded = json_decode($result, true);

		$this->assertIsArray($decoded);
		$this->assertCount(0, $decoded);
	}

	public function testZeroCountTorrent() {
		$scrape = array(
			array(
				'info_hash' => 'abcdef1234567890abcdef1234567890abcdef12',
				'seeders'   => 0,
				'leechers'  => 0,
				'peers'     => 0,
				'size'      => 0,
				'downloads' => 0,
				'traffic'   => 0,
			),
		);

		$result = view_scrape_json($scrape);
		$decoded = json_decode($result, true);
		$torrent = reset($decoded);

		$this->assertEquals(0, $torrent['seeders']);
		$this->assertEquals(0, $torrent['leechers']);
		$this->assertEquals(0, $torrent['peers']);
		$this->assertEquals(0, $torrent['size']);
		$this->assertEquals(0, $torrent['downloads']);
		$this->assertEquals(0, $torrent['traffic']);
	}
}
