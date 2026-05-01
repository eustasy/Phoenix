<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TrackerErrorTest extends PhoenixTestCase {

	public function testEmitsBencodeFailureAndExitsWithCodeTwo(): void {
		// tracker_error() calls exit(2), which would terminate the PHPUnit worker if
		// invoked in-process. Run it in a subprocess and assert against the captured
		// stdout and exit code.
		$functionPath = self::$settings['functions'].'function.tracker.error.php';
		$message      = 'test error';
		$script       = '<?php $settings = '.var_export(self::$settings, true).
			'; require '.var_export($functionPath, true).
			'; tracker_error('.var_export($message, true).');';

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
		$this->assertSame(
			'd14:failure reason'.strlen($message).':'.$message.'e',
			$stdout
		);
	}

}
