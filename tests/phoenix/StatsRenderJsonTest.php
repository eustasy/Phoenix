<?php

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class StatsRenderJsonTest extends TestCase {

	public function testRenderJson() {
		require_once __DIR__.'/../../src/functions/function.stats.render.json.php';

		$stats = array(
			'peers' => 15,
			'seeders' => 10,
			'leechers' => 5,
			'torrents' => 3,
			'downloads' => 100,
			'traffic' => 5000000,
		);
		$settings = array('phoenix_version' => '1.0.0');

		ob_start();
		stats_render_json($stats, $settings);
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey('tracker', $decoded);
		$this->assertEquals('$Id: 1.0.0 $,', $decoded['tracker']['version']);
		$this->assertSame(15, $decoded['tracker']['peers']);
		$this->assertSame(10, $decoded['tracker']['seeders']);
		$this->assertSame(5, $decoded['tracker']['leechers']);
		$this->assertSame(3, $decoded['tracker']['torrents']);
		$this->assertSame(100, $decoded['tracker']['downloads']);
		$this->assertSame(5000000, $decoded['tracker']['traffic']);
	}

}
