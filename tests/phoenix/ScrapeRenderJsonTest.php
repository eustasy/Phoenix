<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ScrapeRenderJsonTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.scrape.render.json.php';
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

	public function testEmptyScrapeYieldsEmptyJsonObject(): void {
		$out = scrape_render_json(array());
		$this->assertSame('[]', $out);
	}

	public function testSingleTorrentDecodesToExpectedShape(): void {
		$out = scrape_render_json($this->fixture());
		$decoded = json_decode($out, true);
		$this->assertIsArray($decoded);
		$hashA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$this->assertArrayHasKey($hashA, $decoded);
		$this->assertSame(array(
			'info_hash' => $hashA,
			'seeders'   => 2,
			'leechers'  => 1,
			'peers'     => 3,
			'size'      => 1024,
			'downloads' => 7,
			'traffic'   => 7168,
		), $decoded[$hashA]);
	}

	public function testKeyedByInfoHash(): void {
		$scrape = $this->fixture();
		$scrape['bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'] = array(
			'info_hash' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
			'seeders'   => 0, 'leechers' => 5, 'peers' => 5,
			'size'      => 0, 'downloads' => 0, 'traffic' => 0,
		);
		$decoded = json_decode(scrape_render_json($scrape), true);
		$this->assertArrayHasKey('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $decoded);
		$this->assertArrayHasKey('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $decoded);
		$this->assertSame(5, $decoded['bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb']['leechers']);
	}

	public function testReturnsValidJsonString(): void {
		$out = scrape_render_json($this->fixture());
		$this->assertNotFalse(json_decode($out));
		$this->assertSame(JSON_ERROR_NONE, json_last_error());
	}

}
