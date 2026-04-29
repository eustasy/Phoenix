<?php

// tracker_error() calls exit(2); use ob_start and a shutdown function to
// assert the bencode output before the process terminates. The shutdown
// function overrides the exit code so the test runner sees 0 (pass) or 1 (fail).
$error_msg = 'test error';
$expected  = 'd14:failure reason'.strlen($error_msg).':'.$error_msg.'e';

ob_start();
register_shutdown_function(function() use ($expected, &$failure) {
	$output = ob_get_clean();
	if ( $output !== $expected ) {
		echo 'Error: Test for Function "tracker_error" failed.'.PHP_EOL;
		$failure = true;
	}
	exit($failure ? 1 : 0);
});
tracker_error($error_msg);
