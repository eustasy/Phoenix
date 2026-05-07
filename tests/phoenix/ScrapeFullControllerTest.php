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

	// Expected per-torrent rendering for HASH: 1 seeder, 1 leecher, size
	// 4096, 3 downloads → peers = 2, traffic = size * downloads = 12288.
	// Full-scrape now mirrors specific-scrape's field set; the previous
	// asymmetry (size always 0 in full-scrape output) was an oversight in
	// torrents_scrape_all that has been corrected.

	private const HASH_BENCODE = 'd8:completei1e10:downloadedi3e10:incompletei1ee';
	private const HASH_XML     =
		'<torrent>'.
		'<info_hash>cccccccccccccccccccccccccccccccccccccccc</info_hash>'.
		'<seeders>1</seeders>'.
		'<leechers>1</leechers>'.
		'<peers>2</peers>'.
		'<size>4096</size>'.
		'<downloads>3</downloads>'.
		'<traffic>12288</traffic>'.
		'</torrent>';
	private const HASH_JSON = [
		'info_hash' => self::HASH,
		'seeders'   => 1,
		'leechers'  => 1,
		'peers'     => 2,
		'size'      => 4096,
		'downloads' => 3,
		'traffic'   => 12288,
	];

	public function testRendersBencodeForFullScrape(): void {
		$_GET    = [];
		$bencode = \scrape_full_controller(self::$connection, self::$settings);

		$this->assertStringStartsWith('d5:files', $bencode);
		$this->assertStringEndsWith('ee',        $bencode);
		// Other torrents from the wider suite may also appear in the
		// response, but our hash should carry exactly these counts.
		$this->assertStringContainsString(
			'20:'.hex2bin(self::HASH).self::HASH_BENCODE,
			$bencode
		);
		// BEP 15 specifies exactly three keys per torrent dict (complete,
		// downloaded, incomplete). Phoenix's XML/JSON renders extend that
		// with peers/size/traffic for caller convenience, but the bencode
		// output must stay strictly conformant — strict BitTorrent clients
		// are within their rights to reject responses with unknown keys.
		$this->assertStringNotContainsString('9:info_hash', $bencode);
		$this->assertStringNotContainsString('5:peers',     $bencode);
		$this->assertStringNotContainsString('4:size',      $bencode);
		$this->assertStringNotContainsString('7:traffic',   $bencode);
	}

	public function testRendersXmlForFullScrape(): void {
		$_GET = ['xml' => '1'];
		$xml  = \scrape_full_controller(self::$connection, self::$settings);

		$this->assertStringStartsWith('<?xml', $xml);
		$this->assertStringContainsString('<scrape>', $xml);
		$this->assertStringContainsString(self::HASH_XML, $xml);
	}

	public function testRendersJsonForFullScrape(): void {
		$_GET = ['json' => '1'];
		$json = \scrape_full_controller(self::$connection, self::$settings);

		$decoded = json_decode($json, true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey(self::HASH, $decoded);
		$this->assertSame(self::HASH_JSON, $decoded[self::HASH]);
	}

}
