<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/scrape.specific.php';

class ScrapeSpecificControllerTest extends PhoenixTestCase {

	// 40-char hex info_hashes (view_scrape_bencode hex2bin's them).
	private const HASH_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	private const HASH_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

	private int $errorReporting;

	/** @var array<string, mixed> */
	private array $getBackup;

	protected function setUp(): void {
		parent::setUp();
		$this->errorReporting = error_reporting();
		$this->getBackup      = $_GET;
		error_reporting(0);

		// Two known torrents: A has data + 2 seeders + 1 leecher,
		// B is registered but has no peers, exercises the zero-init path.
		mysqli_query(
			self::$connection,
			'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
			'(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES '.
			'(\''.self::HASH_A.'\', \'A\', 1024, 1, 7), '.
			'(\''.self::HASH_B.'\', \'B\',    0, 1, 0);'
		);
		mysqli_query(
			self::$connection,
			'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			'(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
			'(\''.self::HASH_A.'\', \'__TEST_specific_seed_1__\', \'\', \'\', 0, 0, 1, '.self::$time.'), '.
			'(\''.self::HASH_A.'\', \'__TEST_specific_seed_2__\', \'\', \'\', 0, 0, 1, '.self::$time.'), '.
			'(\''.self::HASH_A.'\', \'__TEST_specific_leech__\', \'\', \'\', 0, 0, 0, '.self::$time.');'
		);
	}

	protected function tearDown(): void {
		error_reporting($this->errorReporting);
		$_GET = $this->getBackup;
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` IN '.
			'(\''.self::HASH_A.'\', \''.self::HASH_B.'\');'
		);
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` IN '.
			'(\''.self::HASH_A.'\', \''.self::HASH_B.'\');'
		);
		parent::tearDown();
	}

	public function testRendersBencodeByDefault(): void {
		$_GET    = [];
		$bencode = \scrape_specific_controller(self::$connection, self::$settings, [self::HASH_A]);

		// d5:filesd<20:hash><dict>ee. We only assert the shape and the
		// numeric fields; view_scrape_bencode is exhaustively covered
		// elsewhere.
		$this->assertStringStartsWith('d5:files', $bencode);
		$this->assertStringEndsWith('ee',        $bencode);
		$this->assertStringContainsString('8:completei2e',     $bencode); // 2 seeders
		$this->assertStringContainsString('10:incompletei1e',  $bencode); // 1 leecher
		$this->assertStringContainsString('10:downloadedi7e',  $bencode); // 7 downloads
	}

	public function testRendersXmlWhenXmlFlagSet(): void {
		$_GET = ['xml' => '1'];
		$xml  = \scrape_specific_controller(self::$connection, self::$settings, [self::HASH_A]);

		$this->assertStringStartsWith('<?xml', $xml);
		$this->assertStringContainsString('<scrape>', $xml);
		$this->assertStringContainsString('<info_hash>'.self::HASH_A.'</info_hash>', $xml);
		$this->assertStringContainsString('<seeders>2</seeders>',   $xml);
		$this->assertStringContainsString('<leechers>1</leechers>', $xml);
		$this->assertStringContainsString('<downloads>7</downloads>', $xml);
	}

	public function testRendersJsonWhenJsonFlagSet(): void {
		$_GET = ['json' => '1'];
		$json = \scrape_specific_controller(self::$connection, self::$settings, [self::HASH_A]);

		$decoded = json_decode($json, true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey(self::HASH_A, $decoded);
		$this->assertSame(2, $decoded[self::HASH_A]['seeders']);
		$this->assertSame(1, $decoded[self::HASH_A]['leechers']);
		$this->assertSame(7, $decoded[self::HASH_A]['downloads']);
	}

	public function testZeroInitsRequestedHashWithNoPeers(): void {
		// HASH_B is in the torrents table but has no peers — pre-init
		// guarantees it appears in the response with zeros instead of
		// being silently dropped.
		$_GET = ['json' => '1'];
		$json = \scrape_specific_controller(
			self::$connection,
			self::$settings,
			[self::HASH_A, self::HASH_B]
		);
		$decoded = json_decode($json, true);

		$this->assertArrayHasKey(self::HASH_B, $decoded);
		$this->assertSame(0, $decoded[self::HASH_B]['seeders']);
		$this->assertSame(0, $decoded[self::HASH_B]['leechers']);
		$this->assertSame(0, $decoded[self::HASH_B]['peers']);
	}

}
