<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class OnceAnnouncePeerEventTest extends PhoenixTestCase {

	private const HASH = '__TEST_ONCE_APE_HASH__';
	private const PEER = '__TEST_ONCE_APE_PEER__';

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['model'].'peer.insert.php';
	}

	protected function tearDown(): void {
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
		$_GET = array();
	}

	/** @return array<string, mixed> */
	private function fixturePeer(): array {
		return array(
			'info_hash'  => self::HASH,
			'peer_id'    => self::PEER,
			'state'      => 0,
			'left'       => 100,
			'uploaded'   => 0,
			'downloaded' => 0,
			'ipv4'       => '',
			'ipv6'       => '',
			'port'       => '',
			'portv4'     => '0',
			'portv6'     => '0',
		);
	}

	/** @return array<string, mixed>|null */
	private function fetchPeer(): ?array {
		$result = mysqli_query(
			self::$connection,
			'SELECT * FROM `'.self::$settings['db_prefix'].'peers` '.
			'WHERE `info_hash`=\''.self::HASH.'\' AND `peer_id`=\''.self::PEER.'\';'
		);
		if ( !$result || mysqli_num_rows($result) === 0 ) {
			return null;
		}
		return mysqli_fetch_assoc($result);
	}

	/** @return array<string, mixed>|null */
	private function fetchTorrent(): ?array {
		$result = mysqli_query(
			self::$connection,
			'SELECT * FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash`=\''.self::HASH.'\';'
		);
		if ( !$result || mysqli_num_rows($result) === 0 ) {
			return null;
		}
		return mysqli_fetch_assoc($result);
	}

	/** @param array<string, mixed> $peer */
	private function runOnce(array &$peer): void {
		// Onces share scope with their caller; bring the bootstrap globals into
		// local scope so the once's references resolve correctly.
		$connection = self::$connection;
		$settings   = self::$settings;
		$time       = self::$time;
		require $settings['onces'].'once.announce.peer.event.php';
	}

	public function testInsertsNewPeerWhenNoPriorRow(): void {
		$peer = $this->fixturePeer();
		$_GET = array();

		$this->runOnce($peer);

		$row = $this->fetchPeer();
		$this->assertIsArray($row);
		$this->assertSame(self::HASH, $row['info_hash']);
		$this->assertEquals(self::$time, intval($row['updated']));
	}

	public function testReplacesPeerWhenStateChanged(): void {
		$existing = $this->fixturePeer();
		peer_insert(self::$connection, self::$settings, self::$time, $existing);

		$peer = $this->fixturePeer();
		$peer['state'] = 1;
		$_GET = array();

		$this->runOnce($peer);

		$row = $this->fetchPeer();
		$this->assertIsArray($row);
		$this->assertEquals(1, intval($row['state']));
	}

	public function testUpdatesAccessTimeWhenUnchanged(): void {
		$peer = $this->fixturePeer();
		peer_insert(self::$connection, self::$settings, self::$time - 3600, $peer);

		$_GET = array();
		$this->runOnce($peer);

		$row = $this->fetchPeer();
		$this->assertIsArray($row);
		$this->assertEquals(self::$time, intval($row['updated']));
	}

	public function testCompletedEventIncrementsDownloadsAndForcesSeedingState(): void {
		$peer = $this->fixturePeer();
		$_GET = array('event' => 'completed');

		$this->runOnce($peer);

		$torrent = $this->fetchTorrent();
		$this->assertIsArray($torrent);
		$this->assertEquals(1, intval($torrent['downloads']));

		$row = $this->fetchPeer();
		$this->assertIsArray($row);
		$this->assertEquals(1, intval($row['state']));
	}

	public function testStoppedEventDeletesPeerAndExits(): void {
		$peer = $this->fixturePeer();
		peer_insert(self::$connection, self::$settings, self::$time, $peer);

		// The 'stopped' branch of the once calls exit(), which would terminate
		// the PHPUnit worker if invoked in-process. Run it in a subprocess and
		// assert against the resulting database state and exit code.
		$bootstrapPath = realpath(__DIR__.'/../bootstrap.php');
		$script = '<?php'.PHP_EOL.
			'require '.var_export($bootstrapPath, true).';'.PHP_EOL.
			'$connection = $GLOBALS["phoenix_connection"];'.PHP_EOL.
			'$settings   = $GLOBALS["phoenix_settings"];'.PHP_EOL.
			'$time       = $GLOBALS["phoenix_time"];'.PHP_EOL.
			'$peer       = '.var_export($peer, true).';'.PHP_EOL.
			'$_GET       = array("event" => "stopped");'.PHP_EOL.
			'require $settings["onces"]."once.announce.peer.event.php";'.PHP_EOL;

		$tmp = tempnam(sys_get_temp_dir(), 'phx_test_');
		$this->assertNotFalse($tmp);
		file_put_contents($tmp, $script);

		try {
			$proc = proc_open(
				array(PHP_BINARY, $tmp),
				array(
					0 => array('pipe', 'r'),
					1 => array('pipe', 'w'),
					2 => array('pipe', 'w'),
				),
				$pipes
			);
			$this->assertNotFalse($proc);
			fclose($pipes[0]);
			$stdout = stream_get_contents($pipes[1]);
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$exit = proc_close($proc);
		} finally {
			unlink($tmp);
		}

		$this->assertSame(0, $exit, 'subprocess failed. stderr: '.$stderr.' stdout: '.$stdout);
		$this->assertNull($this->fetchPeer());
	}

}
