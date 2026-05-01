<?php

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class AnnounceCheckRateLimitTest extends PhoenixTestCase {

	public function testNoRateLimitWhenNoPeersExist() {
		require_once self::$settings['functions'].'function.announce.check.rate.limit.php';

		$peer = array(
			'info_hash' => str_repeat('a', 40),
			'peer_id'   => str_repeat('1', 40),
			'ipv4'      => '192.0.2.1',
			'ipv6'      => null,
		);

		// Should not throw error
		announce_check_rate_limit(self::$connection, self::$settings, $peer, self::$time);
		$this->assertTrue(true); // If we get here, no error was thrown
	}

	public function testNoRateLimitWhenSamePeerIdExists() {
		require_once self::$settings['functions'].'function.announce.check.rate.limit.php';

		$info_hash = str_repeat('a', 40);
		$peer_id   = str_repeat('1', 40);

		// Insert existing peer with same peer_id
		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			   '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
			   "('".$info_hash."', '".$peer_id."', '192.0.2.1', '', '', '', 6881, 0, '1', ".self::$time.");";
		mysqli_query(self::$connection, $sql);

		$peer = array(
			'info_hash' => $info_hash,
			'peer_id'   => $peer_id,
			'ipv4'      => '192.0.2.1',
			'ipv6'      => null,
		);

		// Should not throw error (same peer_id is excluded from query)
		announce_check_rate_limit(self::$connection, self::$settings, $peer, self::$time);
		$this->assertTrue(true);
	}

	public function testNoRateLimitWhenPeerOutsideTimeWindow() {
		require_once self::$settings['functions'].'function.announce.check.rate.limit.php';

		$info_hash = str_repeat('a', 40);
		$peer_id_1 = str_repeat('1', 40);
		$peer_id_2 = str_repeat('2', 40);

		// Insert peer updated outside the time window (min_interval/5 ago)
		$threshold = self::$time - intval(self::$settings['min_interval'] / 5);
		$old_time = $threshold - 100; // Well outside the window

		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			   '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
			   "('".$info_hash."', '".$peer_id_1."', '192.0.2.1', '', '', '', 6881, 0, '1', ".$old_time.");";
		mysqli_query(self::$connection, $sql);

		$peer = array(
			'info_hash' => $info_hash,
			'peer_id'   => $peer_id_2,
			'ipv4'      => '192.0.2.1',
			'ipv6'      => null,
		);

		// Should not throw error (old peer is outside time window)
		announce_check_rate_limit(self::$connection, self::$settings, $peer, self::$time);
		$this->assertTrue(true);
	}

	public function testRateLimitExceededWithDifferentPeerIdSameIpv4() {
		$info_hash = str_repeat('a', 40);
		$peer_id_1 = str_repeat('1', 40);
		$peer_id_2 = str_repeat('2', 40);

		// Insert recent peer with different peer_id but same IPv4
		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			   '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
			   "('".$info_hash."', '".$peer_id_1."', '192.0.2.1', '', '', '', 6881, 0, '1', ".self::$time.");";
		mysqli_query(self::$connection, $sql);

		$peer = array(
			'info_hash' => $info_hash,
			'peer_id'   => $peer_id_2,
			'ipv4'      => '192.0.2.1',
			'ipv6'      => null,
		);

		// Test in subprocess because tracker_error() calls exit()
		$this->assertRateLimitErrorInSubprocess($peer);
	}

	public function testRateLimitExceededWithDifferentPeerIdSameIpv6() {
		$info_hash = str_repeat('b', 40);
		$peer_id_1 = str_repeat('3', 40);
		$peer_id_2 = str_repeat('4', 40);

		// Insert recent peer with different peer_id but same IPv6
		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			   '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
			   "('".$info_hash."', '".$peer_id_1."', '', 'fc00::1', '', '', 0, 6881, '1', ".self::$time.");";
		mysqli_query(self::$connection, $sql);

		$peer = array(
			'info_hash' => $info_hash,
			'peer_id'   => $peer_id_2,
			'ipv4'      => null,
			'ipv6'      => 'fc00::1',
		);

		// Test in subprocess
		$this->assertRateLimitErrorInSubprocess($peer);
	}

	public function testRateLimitWithBothIpv4AndIpv6() {
		$info_hash = str_repeat('c', 40);
		$peer_id_1 = str_repeat('5', 40);
		$peer_id_2 = str_repeat('6', 40);

		// Insert peer with both IPv4 and IPv6
		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			   '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
			   "('".$info_hash."', '".$peer_id_1."', '192.0.2.100', 'fc00::100', '', '', 6881, 6881, '1', ".self::$time.");";
		mysqli_query(self::$connection, $sql);

		$peer = array(
			'info_hash' => $info_hash,
			'peer_id'   => $peer_id_2,
			'ipv4'      => '192.0.2.100',
			'ipv6'      => 'fc00::100',
		);

		// Test in subprocess
		$this->assertRateLimitErrorInSubprocess($peer);
	}

	public function testNoRateLimitForDifferentTorrent() {
		require_once self::$settings['functions'].'function.announce.check.rate.limit.php';

		$info_hash_1 = str_repeat('a', 40);
		$info_hash_2 = str_repeat('b', 40);
		$peer_id_1   = str_repeat('1', 40);
		$peer_id_2   = str_repeat('2', 40);

		// Insert peer for torrent 1
		$sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
			   '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
			   "('".$info_hash_1."', '".$peer_id_1."', '192.0.2.1', '', '', '', 6881, 0, '1', ".self::$time.");";
		mysqli_query(self::$connection, $sql);

		$peer = array(
			'info_hash' => $info_hash_2, // Different torrent
			'peer_id'   => $peer_id_2,
			'ipv4'      => '192.0.2.1',
			'ipv6'      => null,
		);

		// Should not throw error (different info_hash)
		announce_check_rate_limit(self::$connection, self::$settings, $peer, self::$time);
		$this->assertTrue(true);
	}

	/**
	 * Helper to test rate limit error in subprocess (since tracker_error calls exit).
	 */
	private function assertRateLimitErrorInSubprocess($peer) {
		$functionPath = self::$settings['functions'].'function.announce.check.rate.limit.php';
		$script = '<?php '.
			'$settings = '.var_export(self::$settings, true).'; '.
			'$connection = mysqli_connect('.
			var_export(self::$settings['db_host'], true).', '.
			var_export(self::$settings['db_user'], true).', '.
			var_export(self::$settings['db_pass'], true).', '.
			var_export(self::$settings['db_name'], true).
			'); '.
			'require '.var_export(self::$settings['functions'].'function.tracker.error.php', true).'; '.
			'require '.var_export($functionPath, true).'; '.
			'announce_check_rate_limit($connection, $settings, '.
			var_export($peer, true).', '.var_export(self::$time, true).');';

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
			fclose($pipes[1]);
			fclose($pipes[2]);
			$exit = proc_close($proc);
		} finally {
			unlink($tmp);
		}

		$this->assertSame(2, $exit);
		$this->assertStringContainsString('Announce rate limit exceeded', $stdout);
	}

	protected function tearDown(): void {
		mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `peer_id` LIKE \'__TEST_%\' OR `peer_id` REGEXP \'^[0-9]+$\'');
	}

}
