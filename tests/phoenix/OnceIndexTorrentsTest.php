<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class OnceIndexTorrentsTest extends PhoenixTestCase {

	private const HASH_LISTED   = '__TEST_OIT_LISTED__';
	private const HASH_UNLISTED = '__TEST_OIT_UNLISTED_';

	/** @var array<string, mixed> */
	private array $getBackup = array();

	protected function setUp(): void {
		$this->getBackup = $_GET;
		$_GET = array();
	}

	protected function tearDown(): void {
		$_GET = $this->getBackup;
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
	}

	private function insertTorrent(string $hash, string $name, int $listed, int $downloads = 0, int $size = 1024): void {
		$h = mysqli_real_escape_string(self::$connection, $hash);
		$n = mysqli_real_escape_string(self::$connection, $name);
		mysqli_query(
			self::$connection,
			'INSERT INTO `'.self::$settings['db_prefix'].'torrents`'.
			' (`info_hash`, `name`, `listed`, `downloads`, `size`)'.
			" VALUES ('$h', '$n', $listed, $downloads, $size);"
		);
	}

	private function insertPeer(string $info_hash, string $peer_id, int $state): void {
		$prefix = self::$settings['db_prefix'];
		$h      = mysqli_real_escape_string(self::$connection, $info_hash);
		$p      = mysqli_real_escape_string(self::$connection, $peer_id);
		$t      = self::$time;
		mysqli_query(
			self::$connection,
			"INSERT INTO `{$prefix}peers`" .
			' (`info_hash`, `peer_id`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`)' .
			" VALUES ('$h', '$p', '', '', 6881, 0, $state, $t);"
		);
	}

	private function runOnce(): string {
		$connection = self::$connection;
		$settings   = self::$settings;
		ob_start();
		require $settings['onces'].'once.index.torrents.php';
		return ob_get_clean();
	}

	public function testListedTorrentAppearsInHtmlOutput(): void {
		$this->insertTorrent(self::HASH_LISTED, 'Listed Torrent', 1);

		$output = $this->runOnce();

		$this->assertStringContainsString('Listed Torrent', $output);
	}

	public function testUnlistedTorrentIsExcluded(): void {
		$this->insertTorrent(self::HASH_UNLISTED, 'Unlisted Torrent', 0);

		$output = $this->runOnce();

		$this->assertStringNotContainsString('Unlisted Torrent', $output);
	}

	public function testJsonOutputContainsListedTorrent(): void {
		$this->insertTorrent(self::HASH_LISTED, 'Listed Torrent', 1, 5, 2048);
		$_GET['json'] = '';

		$decoded = json_decode($this->runOnce(), true);

		$this->assertIsArray($decoded);
		$found = null;
		foreach ( $decoded as $t ) {
			if ( $t['info_hash'] === self::HASH_LISTED ) {
				$found = $t;
				break;
			}
		}
		$this->assertNotNull($found, 'Listed torrent not found in JSON output');
		$this->assertSame(5, $found['downloads']);
		$this->assertSame(2048, $found['size']);
	}

	public function testJsonOutputExcludesUnlistedTorrent(): void {
		$this->insertTorrent(self::HASH_LISTED,   'Listed Torrent',   1);
		$this->insertTorrent(self::HASH_UNLISTED, 'Unlisted Torrent', 0);
		$_GET['json'] = '';

		$decoded = json_decode($this->runOnce(), true);

		$hashes = array_column($decoded, 'info_hash');
		$this->assertContains(self::HASH_LISTED,   $hashes);
		$this->assertNotContains(self::HASH_UNLISTED, $hashes);
	}

	public function testXmlOutputContainsListedTorrent(): void {
		$this->insertTorrent(self::HASH_LISTED, 'Listed Torrent', 1);
		$_GET['xml'] = '';

		$output = $this->runOnce();

		$this->assertStringStartsWith('<?xml version="1.0"', $output);
		$this->assertStringContainsString('<info_hash>'.self::HASH_LISTED.'</info_hash>', $output);
	}

	public function testPeerCountsAreSummedByState(): void {
		$this->insertTorrent(self::HASH_LISTED, 'Listed Torrent', 1);
		$this->insertPeer(self::HASH_LISTED, '__TEST_OIT_PEER1__', 1); // seeder
		$this->insertPeer(self::HASH_LISTED, '__TEST_OIT_PEER2__', 1); // seeder
		$this->insertPeer(self::HASH_LISTED, '__TEST_OIT_PEER3__', 0); // leecher
		$_GET['json'] = '';

		$decoded = json_decode($this->runOnce(), true);

		$found = null;
		foreach ( $decoded as $t ) {
			if ( $t['info_hash'] === self::HASH_LISTED ) {
				$found = $t;
				break;
			}
		}
		$this->assertNotNull($found);
		$this->assertSame(2, $found['seeders']);
		$this->assertSame(1, $found['leechers']);
		$this->assertSame(3, $found['peers']);
	}

	public function testTrafficIsSizeTimesDownloads(): void {
		$this->insertTorrent(self::HASH_LISTED, 'Listed Torrent', 1, 7, 1024);
		$_GET['json'] = '';

		$decoded = json_decode($this->runOnce(), true);

		$found = null;
		foreach ( $decoded as $t ) {
			if ( $t['info_hash'] === self::HASH_LISTED ) {
				$found = $t;
				break;
			}
		}
		$this->assertNotNull($found);
		$this->assertSame(7168, $found['traffic']); // 1024 * 7
	}

}
