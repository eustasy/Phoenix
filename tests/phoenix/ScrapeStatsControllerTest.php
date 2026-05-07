<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/scrape.stats.php';

class ScrapeStatsControllerTest extends PhoenixTestCase {

	private int $errorReporting;

	/** @var array<string, mixed> */
	private array $getBackup;

	protected function setUp(): void {
		parent::setUp();
		// Suppress the harmless "headers already sent" warning the
		// controller's header() calls would emit under PHPUnit, and
		// preserve $_GET across tests.
		$this->errorReporting = error_reporting();
		$this->getBackup      = $_GET;
		error_reporting(0);
	}

	protected function tearDown(): void {
		error_reporting($this->errorReporting);
		$_GET = $this->getBackup;
		parent::tearDown();
	}

	public function testRendersHtmlByDefault(): void {
		$_GET = [];
		$html = \scrape_stats_controller(self::$connection, self::$settings);

		$this->assertIsString($html);
		$this->assertStringContainsString('<!DocType html>', $html);
		$this->assertStringContainsString('peers',    $html);
		$this->assertStringContainsString('seeders',  $html);
		$this->assertStringContainsString('leechers', $html);
		$this->assertStringContainsString('torrents', $html);
	}

	public function testRendersXmlWhenXmlFlagSet(): void {
		$_GET = ['xml' => '1'];
		$xml  = \scrape_stats_controller(self::$connection, self::$settings);

		$this->assertStringStartsWith('<?xml', $xml);
		$this->assertStringContainsString('<tracker version=', $xml);
		$this->assertStringContainsString('<peers>',    $xml);
		$this->assertStringContainsString('<seeders>',  $xml);
		$this->assertStringContainsString('<leechers>', $xml);
		$this->assertStringContainsString('<torrents>', $xml);
		$this->assertStringContainsString('</tracker>', $xml);
	}

	public function testRendersJsonWhenJsonFlagSet(): void {
		$_GET = ['json' => '1'];
		$json = \scrape_stats_controller(self::$connection, self::$settings);

		$decoded = json_decode($json, true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey('tracker', $decoded);
		foreach (['peers', 'seeders', 'leechers', 'torrents', 'downloads', 'traffic'] as $key) {
			$this->assertArrayHasKey($key, $decoded['tracker']);
			$this->assertIsInt($decoded['tracker'][$key]);
		}
	}

	public function testXmlBeatsJsonWhenBothFlagsSet(): void {
		// Stats endpoint reads $_GET['xml'] before $_GET['json'], same as
		// tracker_error()'s own format precedence. Pin so a future reorder
		// doesn't silently flip the contract.
		$_GET = ['xml' => '1', 'json' => '1'];
		$out  = \scrape_stats_controller(self::$connection, self::$settings);

		$this->assertStringStartsWith('<?xml', $out);
	}

}
