<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ScrapeOutputTest extends PhoenixTestCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		$settings = self::$settings;
		require_once $settings['functions'].'function.scrape.output.php';
	}

	public function testOutputsBencodeByDefault(): void
	{
		// Mock scrape data
		$scrape = [
			'abc' => [
				'info_hash' => 'abc',
				'seeders' => 1,
				'leechers' => 2,
				'peers' => 3,
				'size' => 123,
				'downloads' => 4,
				'traffic' => 492,
			],
		];
		ob_start();
		try {
			scrape_output($scrape);
			$out = ob_get_clean();
		} catch (\Throwable $e) {
			ob_end_clean();
			throw $e;
		}
		$this->assertNotEmpty($out);
		// Should be bencoded (d...e)
		$this->assertStringStartsWith('d', $out);
	}

	public function testOutputsJsonIfRequested(): void
	{
		$_GET['json'] = 1;
		$scrape = [ 'abc' => ['info_hash' => 'abc', 'seeders' => 1, 'leechers' => 2, 'peers' => 3, 'size' => 123, 'downloads' => 4, 'traffic' => 492] ];
		ob_start();
		try {
			scrape_output($scrape);
			$out = ob_get_clean();
		} catch (\Throwable $e) {
			ob_end_clean();
			throw $e;
		} finally {
			unset($_GET['json']);
		}
		$this->assertJson($out);
	}

	public function testOutputsXmlIfRequested(): void
	{
		$_GET['xml'] = 1;
		$scrape = [ 'abc' => ['info_hash' => 'abc', 'seeders' => 1, 'leechers' => 2, 'peers' => 3, 'size' => 123, 'downloads' => 4, 'traffic' => 492] ];
		ob_start();
		try {
			scrape_output($scrape);
			$out = ob_get_clean();
		} catch (\Throwable $e) {
			ob_end_clean();
			throw $e;
		} finally {
			unset($_GET['xml']);
		}
		$this->assertStringContainsString('<?xml', $out);
	}
}
