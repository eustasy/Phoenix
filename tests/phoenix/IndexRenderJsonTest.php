<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class IndexRenderJsonTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.index.render.json.php';
	}

	/** @return list<array<string, mixed>> */
	private function fixture(): array {
		return array(array(
			'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
			'name'      => 'Test Torrent',
			'size'      => 1024,
			'downloads' => 7,
			'seeders'   => 2,
			'leechers'  => 1,
			'peers'     => 3,
			'traffic'   => 7168,
		));
	}

	public function testEmptyIndexProducesEmptyJsonArray(): void {
		$this->assertSame('[]', index_render_json(array()));
	}

	public function testOutputIsValidJson(): void {
		$decoded = json_decode(index_render_json($this->fixture()), true);
		$this->assertIsArray($decoded);
	}

	public function testSingleTorrentContainsAllFields(): void {
		$decoded = json_decode(index_render_json($this->fixture()), true);
		$this->assertCount(1, $decoded);
		$t = $decoded[0];
		$this->assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $t['info_hash']);
		$this->assertSame('Test Torrent', $t['name']);
		$this->assertSame(1024, $t['size']);
		$this->assertSame(7, $t['downloads']);
		$this->assertSame(2, $t['seeders']);
		$this->assertSame(1, $t['leechers']);
		$this->assertSame(3, $t['peers']);
		$this->assertSame(7168, $t['traffic']);
	}

	public function testMultipleTorrentsPreserveOrder(): void {
		$index = array(
			array(
				'info_hash' => 'aaaa', 'name' => 'Alpha',
				'size' => 0, 'downloads' => 0, 'seeders' => 0,
				'leechers' => 0, 'peers' => 0, 'traffic' => 0,
			),
			array(
				'info_hash' => 'bbbb', 'name' => 'Beta',
				'size' => 0, 'downloads' => 0, 'seeders' => 0,
				'leechers' => 0, 'peers' => 0, 'traffic' => 0,
			),
		);
		$decoded = json_decode(index_render_json($index), true);
		$this->assertSame('Alpha', $decoded[0]['name']);
		$this->assertSame('Beta',  $decoded[1]['name']);
	}

}
