<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ScrapeInitializeResultsTest extends TestCase {

	public function testInitializeResultsWithSingleHash() {
		require_once __DIR__.'/../../src/functions/scrape.initialize.results.php';

		$info_hashes = array(str_repeat('a', 40));

		$result = scrape_initialize_results($info_hashes);

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
		$this->assertArrayHasKey(str_repeat('a', 40), $result);
		
		$entry = $result[str_repeat('a', 40)];
		$this->assertSame(str_repeat('a', 40), $entry['info_hash']);
		$this->assertSame(0, $entry['seeders']);
		$this->assertSame(0, $entry['leechers']);
		$this->assertSame(0, $entry['downloads']);
		$this->assertSame(0, $entry['peers']);
		$this->assertSame(0, $entry['size']);
		$this->assertSame(0, $entry['traffic']);
	}

	public function testInitializeResultsWithMultipleHashes() {
		require_once __DIR__.'/../../src/functions/scrape.initialize.results.php';

		$info_hashes = array(
			str_repeat('a', 40),
			str_repeat('b', 40),
			str_repeat('c', 40),
		);

		$result = scrape_initialize_results($info_hashes);

		$this->assertIsArray($result);
		$this->assertCount(3, $result);
		
		foreach ($info_hashes as $hash) {
			$this->assertArrayHasKey($hash, $result);
			$entry = $result[$hash];
			$this->assertSame($hash, $entry['info_hash']);
			$this->assertSame(0, $entry['seeders']);
			$this->assertSame(0, $entry['leechers']);
			$this->assertSame(0, $entry['downloads']);
			$this->assertSame(0, $entry['peers']);
			$this->assertSame(0, $entry['size']);
			$this->assertSame(0, $entry['traffic']);
		}
	}

	public function testInitializeResultsWithEmptyArray() {
		require_once __DIR__.'/../../src/functions/scrape.initialize.results.php';

		$info_hashes = array();

		$result = scrape_initialize_results($info_hashes);

		$this->assertIsArray($result);
		$this->assertCount(0, $result);
	}

}
