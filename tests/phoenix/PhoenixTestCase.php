<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use mysqli;
use PHPUnit\Framework\TestCase;

abstract class PhoenixTestCase extends TestCase {

	protected static mysqli $connection;

	/** @var array<string, mixed> */
	protected static array $settings;

	protected static int $time;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		self::$connection = $GLOBALS['phoenix_connection'];
		self::$settings   = $GLOBALS['phoenix_settings'];
		self::$time       = $GLOBALS['phoenix_time'];
	}

	/**
	 * Run PHP script in subprocess and capture output/exit code.
	 *
	 * @param string $script PHP code to execute
	 * @return array{stdout: string, stderr: string, exit: int}
	 */
	protected function runPhpSubprocess(string $script): array {
		$tmp = tempnam(sys_get_temp_dir(), 'phx_test_');
		$this->assertNotFalse($tmp);
		file_put_contents($tmp, $script);

		try {
			$proc = proc_open(
				[PHP_BINARY, $tmp],
				[
					0 => ['pipe', 'r'],
					1 => ['pipe', 'w'],
					2 => ['pipe', 'w'],
				],
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

		return [
			'stdout' => $stdout,
			'stderr' => $stderr,
			'exit'   => $exit,
		];
	}

}
