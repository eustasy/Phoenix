<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class OnceSanitizeAnnounceAddressTest extends PhoenixTestCase {

	/** @var array<string, mixed> */
	private array $serverBackup = array();

	protected function setUp(): void {
		$this->serverBackup = $_SERVER;
	}

	protected function tearDown(): void {
		$_SERVER = $this->serverBackup;
		$_GET = array();
	}

	/**
	 * @param array<string, mixed>             $peer
	 * @param array<string, mixed>             $settingsOverride
	 */
	private function runOnce(array &$peer, array $settingsOverride = array()): void {
		// Onces share scope with their caller; bring bootstrap globals in as locals.
		$connection = self::$connection;
		$settings   = array_merge(self::$settings, array(
			'external_ip' => false,
			'honor_xff'   => false,
		), $settingsOverride);
		$time       = self::$time;
		require $settings['onces'].'once.sanitize.announce.address.php';
	}

	public function testResolvesIpv4FromRemoteAddr(): void {
		$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
		$_GET = array('port' => '6881');

		$peer = array();
		$this->runOnce($peer);

		$this->assertSame('192.0.2.1', $peer['ipv4']);
		$this->assertFalse($peer['ipv6']);
	}

	public function testKeepsAddressPortOverGetPortFallback(): void {
		$_SERVER['REMOTE_ADDR'] = '192.0.2.1:9999';
		$_GET = array('port' => '6881');

		$peer = array();
		$this->runOnce($peer);

		$this->assertSame(9999, $peer['portv4']);
	}

	public function testFallsBackToGetPortWhenAddressLacksPort(): void {
		$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
		$_GET = array('port' => '6881');

		$peer = array();
		$this->runOnce($peer);

		$this->assertSame(6881, $peer['portv4']);
		// Fallback also fills portv6 even with no IPv6 candidate (existing behaviour).
		$this->assertSame(6881, $peer['portv6']);
	}

	public function testIgnoresGetIpByDefault(): void {
		// REMOTE_ADDR is IPv6-only; without external_ip no IPv4 should resolve.
		$_SERVER['REMOTE_ADDR'] = '2001:db8::1';
		$_GET = array('ip' => '203.0.113.5');

		$peer = array();
		$this->runOnce($peer);

		$this->assertSame('2001:db8::1', $peer['ipv6']);
		$this->assertFalse($peer['ipv4']);
	}

	public function testHonorsGetIpWhenExternalIpEnabled(): void {
		// REMOTE_ADDR provides IPv6; client supplies IPv4 via ?ip=.
		$_SERVER['REMOTE_ADDR'] = '2001:db8::1';
		$_GET = array('ip' => '203.0.113.5');

		$peer = array();
		$this->runOnce($peer, array('external_ip' => true));

		$this->assertSame('2001:db8::1', $peer['ipv6']);
		$this->assertSame('203.0.113.5', $peer['ipv4']);
	}

	public function testIgnoresXForwardedForByDefault(): void {
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5';
		$_GET = array();

		$peer = array();
		$this->runOnce($peer);

		$this->assertSame('10.0.0.1', $peer['ipv4']);
	}

	public function testHonorsXForwardedForWhenEnabled(): void {
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5';
		$_GET = array();

		$peer = array();
		$this->runOnce($peer, array('honor_xff' => true));

		$this->assertSame('203.0.113.5', $peer['ipv4']);
	}

	public function testCallsTrackerErrorWhenNoAddressesAvailable(): void {
		// tracker_error() calls exit(2); run the once in a subprocess and assert
		// against the captured stdout (bencode failure body) and exit code.
		$bootstrapPath = realpath(__DIR__.'/../bootstrap.php');
		$script = '<?php'.PHP_EOL.
			'require '.var_export($bootstrapPath, true).';'.PHP_EOL.
			'$connection = $GLOBALS["phoenix_connection"];'.PHP_EOL.
			'$settings   = $GLOBALS["phoenix_settings"];'.PHP_EOL.
			'$settings["external_ip"] = false;'.PHP_EOL.
			'$settings["honor_xff"]   = false;'.PHP_EOL.
			'$time       = $GLOBALS["phoenix_time"];'.PHP_EOL.
			'$peer       = array();'.PHP_EOL.
			'$_GET       = array();'.PHP_EOL.
			'$_SERVER    = array();'.PHP_EOL.
			'require $settings["onces"]."once.sanitize.announce.address.php";'.PHP_EOL;

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
		$this->assertStringContainsString('failure reason', $stdout);
		$this->assertStringContainsString('Unable to obtain client IP', $stdout);
	}

}
