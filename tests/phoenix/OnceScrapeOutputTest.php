<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use mysqli_result;

class OnceScrapeOutputTest extends PhoenixTestCase {

	private const HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	/** @var array<string, mixed> */
	private array $getBackup = array();

	protected function setUp(): void {
		$this->getBackup = $_GET;
		$_GET = array();
	}

	protected function tearDown(): void {
		$_GET = $this->getBackup;
	}

	private function syntheticPeers(): mysqli_result {
		$result = mysqli_query(self::$connection,
			"SELECT '".self::HASH."' AS `info_hash`, 2 AS `seeders`, 1 AS `leechers`"
		);
		$this->assertInstanceOf(mysqli_result::class, $result);
		return $result;
	}

	private function syntheticTorrents(): mysqli_result {
		$result = mysqli_query(self::$connection,
			"SELECT '".self::HASH."' AS `info_hash`, 1024 AS `size`, 7 AS `downloads`"
		);
		$this->assertInstanceOf(mysqli_result::class, $result);
		return $result;
	}

	private function runOnce(): string {
		$connection = self::$connection;
		$settings   = self::$settings;
		$peers      = $this->syntheticPeers();
		$torrents   = $this->syntheticTorrents();
		$scrape     = array();
		ob_start();
		require $settings['onces'].'once.scrape.output.php';
		return ob_get_clean();
	}

	public function testDefaultIsBencode(): void {
		$output = $this->runOnce();
		$this->assertStringStartsWith('d5:files', $output);
		$this->assertStringContainsString('8:completei2e', $output);
		$this->assertStringContainsString('10:downloadedi7e', $output);
		$this->assertStringContainsString('10:incompletei1e', $output);
		$this->assertStringContainsString('20:'.hex2bin(self::HASH), $output);
	}

	public function testXmlBranchProducesXmlBody(): void {
		$_GET['xml'] = '';
		$output = $this->runOnce();
		$this->assertStringStartsWith('<?xml version="1.0"', $output);
		$this->assertStringContainsString('<info_hash>'.self::HASH.'</info_hash>', $output);
		$this->assertStringContainsString('<traffic>7168</traffic>', $output);
	}

	public function testJsonBranchProducesValidJson(): void {
		$_GET['json'] = '';
		$output = $this->runOnce();
		$decoded = json_decode($output, true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey(self::HASH, $decoded);
		$this->assertSame(2, $decoded[self::HASH]['seeders']);
		$this->assertSame(1, $decoded[self::HASH]['leechers']);
		$this->assertSame(7168, $decoded[self::HASH]['traffic']);
	}

	public function testPreInitialisedScrapeIsCarriedThrough(): void {
		// Mirror once.scrape.torrent.php: requested-but-unknown hash should still
		// appear in output as zeros after the merge.
		$other = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
		$connection = self::$connection;
		$settings   = self::$settings;
		$peers      = $this->syntheticPeers();
		$torrents   = $this->syntheticTorrents();
		$scrape     = array($other => array(
			'info_hash' => $other,
			'seeders'   => 0, 'leechers' => 0, 'downloads' => 0,
			'peers'     => 0, 'size'     => 0, 'traffic'   => 0,
		));
		$_GET['json'] = '';
		ob_start();
		require $settings['onces'].'once.scrape.output.php';
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertArrayHasKey($other, $decoded);
		$this->assertSame(0, $decoded[$other]['seeders']);
		$this->assertSame(0, $decoded[$other]['traffic']);
	}

}
