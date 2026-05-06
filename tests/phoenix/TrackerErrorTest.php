<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TrackerErrorTest extends PhoenixTestCase {

	public function testEmitsBencodeFailureAndExitsWithCodeTwo(): void {
		// tracker_error() calls exit(2), which would terminate the PHPUnit worker if
		// invoked in-process. Run it in a subprocess and assert against the captured
		// stdout and exit code.
		$functionPath = __DIR__.'/../../src/functions/function.tracker.error.php';
		$message      = 'test error';
		$script       = '<?php $settings = '.var_export(self::$settings, true).
			'; require '.var_export($functionPath, true).
			'; tracker_error('.var_export($message, true).');';

		$result = $this->runPhpSubprocess($script);

		$this->assertSame(2, $result['exit']);
		$this->assertSame(
			'd14:failure reason'.strlen($message).':'.$message.'e',
			$result['stdout']
		);
	}

}
