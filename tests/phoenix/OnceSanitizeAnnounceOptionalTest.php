<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class OnceSanitizeAnnounceOptionalTest extends PhoenixTestCase {

	protected function tearDown(): void {
		$_GET = array();
	}

	/**
	 * @param array<string, mixed> $peer
	 * @param array<string, mixed> $settingsOverride
	 */
	private function runOnce(array &$peer, array $settingsOverride = array()): void {
		$connection = self::$connection;
		$settings   = array_merge(self::$settings, array(
			'default_compact' => false,
			'default_peers'   => 50,
			'max_peers'       => 200,
		), $settingsOverride);
		$time       = self::$time;
		require $settings['onces'].'once.sanitize.announce.optional.php';
	}

	public function testMergesParsedOptionalsIntoPeerWithoutClobberingExistingKeys(): void {
		$_GET = array(
			'left'       => '5',
			'compact'    => '1',
			'no_peer_id' => '1',
			'uploaded'   => '100',
			'downloaded' => '200',
			'numwant'    => '60',
		);
		$peer = array('info_hash' => 'X', 'peer_id' => 'Y');

		$this->runOnce($peer);

		$this->assertSame(5,   $peer['left']);
		$this->assertSame(0,   $peer['state']);
		$this->assertSame(1,   $peer['compact']);
		$this->assertSame(1,   $peer['no_peer_id']);
		$this->assertSame(100, $peer['uploaded']);
		$this->assertSame(200, $peer['downloaded']);
		$this->assertSame(60,  $peer['numwant']);
		// Pre-existing keys preserved.
		$this->assertSame('X', $peer['info_hash']);
		$this->assertSame('Y', $peer['peer_id']);
	}

	public function testAppliesDefaultsWhenGetIsEmpty(): void {
		$_GET = array();
		$peer = array();

		$this->runOnce($peer);

		$this->assertSame(-1, $peer['left']);
		$this->assertSame(0,  $peer['state']);
		$this->assertSame(0,  $peer['compact']);
		$this->assertSame(0,  $peer['no_peer_id']);
		$this->assertSame(0,  $peer['uploaded']);
		$this->assertSame(0,  $peer['downloaded']);
		$this->assertSame(50, $peer['numwant']);
	}

}
