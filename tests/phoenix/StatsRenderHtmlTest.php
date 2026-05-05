<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class StatsRenderHtmlTest extends TestCase {

	public function testRenderHtml() {
		require_once __DIR__.'/../../src/views/html.stats.php';

		$stats = array(
			'peers' => 15,
			'seeders' => 10,
			'leechers' => 5,
			'torrents' => 3,
			'downloads' => 100,
			'traffic' => 5000000,
		);
		$settings = array('phoenix_version' => '1.0.0');

		$output = view_stats_html($stats, $settings);

		$this->assertStringContainsString('<!DocType html>', $output);
		$this->assertStringContainsString('<title>Phoenix: $Id: 1.0.0 $</title>', $output);
		$this->assertStringContainsString('15 peers', $output);
		$this->assertStringContainsString('10 seeders', $output);
		$this->assertStringContainsString('5 leechers', $output);
		$this->assertStringContainsString('3 torrents', $output);
		$this->assertStringContainsString('100 downloads', $output);
		$this->assertStringContainsString('5,000,000 bytes', $output);
	}

	public function testRenderHtmlWithZeroStats() {
		require_once __DIR__.'/../../src/views/html.stats.php';

		$stats = array(
			'peers' => 0,
			'seeders' => 0,
			'leechers' => 0,
			'torrents' => 0,
			'downloads' => 0,
			'traffic' => 0,
		);
		$settings = array('phoenix_version' => '1.0.0');

		$output = view_stats_html($stats, $settings);

		$this->assertStringContainsString('0 peers', $output);
		$this->assertStringContainsString('0 seeders', $output);
		$this->assertStringContainsString('0 leechers', $output);
		$this->assertStringContainsString('0 torrents', $output);
		$this->assertStringContainsString('0 downloads', $output);
		$this->assertStringContainsString('0 bytes', $output);
	}

	public function testRenderHtmlFormatsLargeNumbers() {
		require_once __DIR__.'/../../src/views/html.stats.php';

		$stats = array(
			'peers' => 1234567,
			'seeders' => 654321,
			'leechers' => 580246,
			'torrents' => 9876,
			'downloads' => 543210,
			'traffic' => 9876543210,
		);
		$settings = array('phoenix_version' => '1.0.0');

		$output = view_stats_html($stats, $settings);

		// Check that number_format() is working (US English thousands separator)
		$this->assertStringContainsString('1,234,567 peers', $output);
		$this->assertStringContainsString('654,321 seeders', $output);
		$this->assertStringContainsString('580,246 leechers', $output);
		$this->assertStringContainsString('9,876 torrents', $output);
		$this->assertStringContainsString('543,210 downloads', $output);
		$this->assertStringContainsString('9,876,543,210 bytes', $output);
	}

}
