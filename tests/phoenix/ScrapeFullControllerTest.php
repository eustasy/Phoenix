<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/scrape.full.php';

class ScrapeFullControllerTest extends PhoenixTestCase {

	// 40-char hex info_hash (view_scrape_bencode hex2bin's it).
	private const HASH = 'cccccccccccccccccccccccccccccccccccccccc';

	private int $errorReporting;

	/** @var array<string, mixed> */
	private array $getBackup;

	protected function setUp(): void {
		parent::setUp();
		$this->errorReporting = error_reporting();
		$this->getBackup      = $_GET;
		error_reporting(0);

		// Single known torrent we can pin assertions on. Other rows from
		// the wider suite may also exist; assertions only check our hash.
		mysqli_query(
			self::$connection,
			'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
			'(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES '.
			'(\''.self::HASH.'\', \'Full\', 4096, 1, 3);'
		);
		mysqli_query(
			self::$connection,
			'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			'(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
			'(\''.self::HASH.'\', \'__TEST_full_seed__\',  \'\', \'\', 0, 0, 1, '.self::$time.'), '.
			'(\''.self::HASH.'\', \'__TEST_full_leech__\', \'\', \'\', 0, 0, 0, '.self::$time.');'
		);
	}

	protected function tearDown(): void {
		error_reporting($this->errorReporting);
		$_GET = $this->getBackup;
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.self::HASH.'\';'
		);
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` = \''.self::HASH.'\';'
		);
		parent::tearDown();
	}

	public function testRendersBencodeByDefault(): void {
		$_GET    = [];
		$bencode = \scrape_full_controller(self::$connection, self::$settings);

		$this->assertStringStartsWith('d5:files', $bencode);
		$this->assertStringEndsWith('ee',        $bencode);
		// Each torrent dict carries its own counts; assert the inserted
		// numbers appear somewhere in the response. Other torrents in
		// the wider suite may add other dicts but won't have these
		// exact-shape combinations against our hash.
		$this->assertStringContainsString(hex2bin(self::HASH).'d8:completei1e10:downloadedi3e10:incompletei1ee', $bencode);
	}

	public function testRendersXmlWhenXmlFlagSet(): void {
		$_GET = ['xml' => '1'];
		$xml  = \scrape_full_controller(self::$connection, self::$settings);

		$this->assertStringStartsWith('<?xml', $xml);
		$this->assertStringContainsString('<scrape>', $xml);
		$this->assertStringContainsString('<info_hash>'.self::HASH.'</info_hash>', $xml);
		$this->assertStringContainsString('<seeders>1</seeders>',   $xml);
		$this->assertStringContainsString('<leechers>1</leechers>', $xml);
		$this->assertStringContainsString('<downloads>3</downloads>', $xml);
	}

	public function testRendersJsonWhenJsonFlagSet(): void {
		$_GET = ['json' => '1'];
		$json = \scrape_full_controller(self::$connection, self::$settings);

		$decoded = json_decode($json, true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey(self::HASH, $decoded);
		$this->assertSame(1, $decoded[self::HASH]['seeders']);
		$this->assertSame(1, $decoded[self::HASH]['leechers']);
		$this->assertSame(3, $decoded[self::HASH]['downloads']);
		$this->assertSame(4096, $decoded[self::HASH]['size']);
	}

}
