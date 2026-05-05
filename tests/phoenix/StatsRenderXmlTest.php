<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class StatsRenderXmlTest extends TestCase {

	public function testRenderXml() {
		require_once __DIR__.'/../../src/views/xml.stats.php';

		$stats = array(
			'peers' => 15,
			'seeders' => 10,
			'leechers' => 5,
			'torrents' => 3,
			'downloads' => 100,
			'traffic' => 5000000,
		);
		$settings = array('phoenix_version' => '1.0.0');

		$output = view_stats_xml($stats, $settings);

		$this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>', $output);
		$this->assertStringContainsString('<tracker version="$Id: 1.0.0 $">', $output);
		$this->assertStringContainsString('<peers>15</peers>', $output);
		$this->assertStringContainsString('<seeders>10</seeders>', $output);
		$this->assertStringContainsString('<leechers>5</leechers>', $output);
		$this->assertStringContainsString('<torrents>3</torrents>', $output);
		$this->assertStringContainsString('<downloads>100</downloads>', $output);
		$this->assertStringContainsString('<traffic>5000000</traffic>', $output);
		$this->assertStringContainsString('</tracker>', $output);
	}

}
