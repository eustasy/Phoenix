<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewStatsJsonTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['views'].'json.stats.php';
	}

	public function testReturnsValidJson() {
		$stats = array(
			'peers'     => 42,
			'seeders'   => 30,
			'leechers'  => 12,
			'torrents'  => 5,
			'downloads' => 150,
			'traffic'   => 1073741824, // 1 GB
		);

		$result = view_stats_json($stats, self::$settings);

		$this->assertJson($result, 'Output should be valid JSON');
	}

	public function testIncludesTrackerObject() {
		$stats = array(
			'peers'     => 42,
			'seeders'   => 30,
			'leechers'  => 12,
			'torrents'  => 5,
			'downloads' => 150,
			'traffic'   => 1073741824,
		);

		$result = view_stats_json($stats, self::$settings);
		$decoded = json_decode($result, true);

		$this->assertArrayHasKey('tracker', $decoded);
		$this->assertIsArray($decoded['tracker']);
	}

	public function testIncludesAllStatFields() {
		$stats = array(
			'peers'     => 42,
			'seeders'   => 30,
			'leechers'  => 12,
			'torrents'  => 5,
			'downloads' => 150,
			'traffic'   => 1073741824,
		);

		$result = view_stats_json($stats, self::$settings);
		$decoded = json_decode($result, true);
		$tracker = $decoded['tracker'];

		$this->assertArrayHasKey('version', $tracker);
		$this->assertArrayHasKey('peers', $tracker);
		$this->assertArrayHasKey('seeders', $tracker);
		$this->assertArrayHasKey('leechers', $tracker);
		$this->assertArrayHasKey('torrents', $tracker);
		$this->assertArrayHasKey('downloads', $tracker);
		$this->assertArrayHasKey('traffic', $tracker);
	}

	public function testCorrectStatValues() {
		$stats = array(
			'peers'     => 42,
			'seeders'   => 30,
			'leechers'  => 12,
			'torrents'  => 5,
			'downloads' => 150,
			'traffic'   => 1073741824,
		);

		$result = view_stats_json($stats, self::$settings);
		$decoded = json_decode($result, true);
		$tracker = $decoded['tracker'];

		$this->assertEquals(42, $tracker['peers']);
		$this->assertEquals(30, $tracker['seeders']);
		$this->assertEquals(12, $tracker['leechers']);
		$this->assertEquals(5, $tracker['torrents']);
		$this->assertEquals(150, $tracker['downloads']);
		$this->assertEquals(1073741824, $tracker['traffic']);
	}

	public function testVersionIncludesPhoenixVersion() {
		$stats = array(
			'peers'     => 0,
			'seeders'   => 0,
			'leechers'  => 0,
			'torrents'  => 0,
			'downloads' => 0,
			'traffic'   => 0,
		);

		$result = view_stats_json($stats, self::$settings);
		$decoded = json_decode($result, true);

		$this->assertStringContainsString(
			self::$settings['phoenix_version'],
			$decoded['tracker']['version']
		);
	}

	public function testHandlesZeroStats() {
		$stats = array(
			'peers'     => 0,
			'seeders'   => 0,
			'leechers'  => 0,
			'torrents'  => 0,
			'downloads' => 0,
			'traffic'   => 0,
		);

		$result = view_stats_json($stats, self::$settings);
		$decoded = json_decode($result, true);
		$tracker = $decoded['tracker'];

		$this->assertEquals(0, $tracker['peers']);
		$this->assertEquals(0, $tracker['seeders']);
		$this->assertEquals(0, $tracker['leechers']);
		$this->assertEquals(0, $tracker['torrents']);
		$this->assertEquals(0, $tracker['downloads']);
		$this->assertEquals(0, $tracker['traffic']);
	}
}
