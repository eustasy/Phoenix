<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use mysqli;

class OnceDbConnectTest extends PhoenixTestCase {

	/**
	 * Returns a working settings copy with any 'p:' prefix stripped from
	 * db_host. The bootstrap may have already prefixed it (db_persist_host is
	 * not idempotent), so each test needs a known starting state.
	 *
	 * @return array<string, mixed>
	 */
	private function freshSettings(): array {
		$settings = self::$settings;
		if ( strncmp($settings['db_host'], 'p:', 2) === 0 ) {
			$settings['db_host'] = substr($settings['db_host'], 2);
		}
		return $settings;
	}

	/**
	 * Runs the once with the given settings (passed by reference so the test
	 * can observe in-place mutations to db_host) and returns the resulting
	 * mysqli connection.
	 *
	 * @param array<string, mixed> $settings
	 */
	private function runOnce(array &$settings): ?mysqli {
		$connection = null;
		require $settings['onces'].'once.db.connect.php';
		return $connection instanceof mysqli ? $connection : null;
	}

	public function testSuccessfullyOpensConnection(): void {
		$settings = $this->freshSettings();
		$settings['db_persist'] = false;

		$connection = $this->runOnce($settings);

		$this->assertInstanceOf(mysqli::class, $connection);
	}

	public function testPersistTrueAddsHostPrefix(): void {
		$settings = $this->freshSettings();
		$settings['db_persist'] = true;
		$expected = 'p:'.$settings['db_host'];

		$this->runOnce($settings);

		$this->assertSame($expected, $settings['db_host']);
	}

	public function testPersistFalseLeavesHostUnchanged(): void {
		$settings = $this->freshSettings();
		$settings['db_persist'] = false;
		$original = $settings['db_host'];

		$this->runOnce($settings);

		$this->assertSame($original, $settings['db_host']);
	}

	public function testPersistConnectionStillSucceeds(): void {
		$settings = $this->freshSettings();
		$settings['db_persist'] = true;

		$connection = $this->runOnce($settings);

		$this->assertInstanceOf(mysqli::class, $connection);
		$this->assertStringStartsWith('p:', $settings['db_host']);
	}

	public function testTruthyNonBoolPersistTriggersPrefix(): void {
		// once.db.connect casts db_persist with (bool), so loose-truthy values
		// like the string '1' must also enable the persistent prefix. Pin
		// that contract so future refactors don't quietly tighten it.
		$settings = $this->freshSettings();
		$settings['db_persist'] = '1';
		$expected = 'p:'.$settings['db_host'];

		$this->runOnce($settings);

		$this->assertSame($expected, $settings['db_host']);
	}

	public function testConnectionFailureCallsTrackerError(): void {
		// PHP 8.1+ mysqli_connect throws mysqli_sql_exception on failure. The once
		// must catch it and emit a bencode error rather than crashing. Drive a
		// guaranteed-bad connection in a subprocess and assert on stdout/exit.
		$script = '<?php '.PHP_EOL.
			'$settings = array('.PHP_EOL.
			'    \'functions\' => '.var_export(self::$settings['functions'], true).','.PHP_EOL.
			'    \'onces\'     => '.var_export(self::$settings['onces'], true).','.PHP_EOL.
			'    \'db_host\'   => \'127.0.0.1\','.PHP_EOL.
			'    \'db_user\'   => \'__phx_no_such_user__\','.PHP_EOL.
			'    \'db_pass\'   => \'wrong\','.PHP_EOL.
			'    \'db_name\'   => \'__phx_no_such_db__\','.PHP_EOL.
			'    \'db_persist\' => false,'.PHP_EOL.
			');'.PHP_EOL.
			'require $settings[\'functions\'].\'function.tracker.error.php\';'.PHP_EOL.
			'require $settings[\'onces\'].\'once.db.connect.php\';'.PHP_EOL;

		[$stdout, $exit] = $this->runScript($script);

		$this->assertSame(2, $exit);
		$this->assertStringContainsString('may be mis-configured', $stdout);
		$this->assertStringStartsWith('d14:failure reason', $stdout);
	}

	public function testNotConfiguredCallsTrackerError(): void {
		// tracker_error() calls exit(2). Running in-process would terminate the
		// PHPUnit worker, so spawn a subprocess and assert against captured
		// stdout + exit code (see TrackerErrorTest for the same approach).
		$script = '<?php '.PHP_EOL.
			'$settings = array('.PHP_EOL.
			'    \'functions\' => '.var_export(self::$settings['functions'], true).','.PHP_EOL.
			'    \'onces\'     => '.var_export(self::$settings['onces'], true).','.PHP_EOL.
			'    \'db_host\'   => \'\','.PHP_EOL.
			'    \'db_user\'   => \'\','.PHP_EOL.
			'    \'db_name\'   => \'\','.PHP_EOL.
			'    \'db_persist\' => false,'.PHP_EOL.
			');'.PHP_EOL.
			'require $settings[\'functions\'].\'function.tracker.error.php\';'.PHP_EOL.
			'require $settings[\'onces\'].\'once.db.connect.php\';'.PHP_EOL;

		[$stdout, $exit] = $this->runScript($script);

		$this->assertSame(2, $exit);
		$this->assertStringContainsString('Tracker is not configured', $stdout);
		$this->assertStringStartsWith('d14:failure reason', $stdout);
	}

	/** @return array{0: string, 1: int} */
	private function runScript(string $script): array {
		$tmp = tempnam(sys_get_temp_dir(), 'phx_odc_');
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

		return array((string)$stdout, $exit);
	}

}
