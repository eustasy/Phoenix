<?php

// tracker_error() calls exit(2), so exercise it in a child PHP process and
// assert both the bencoded output and the exit status. Overriding the exit
// code from a shutdown function instead is unreliable on PHP 7.x when the
// script runs from a file, and exiting here would also stop the test runner
// before later tests and its own failure accounting.
$error_msg = 'test error';
$expected  = 'd14:failure reason'.strlen($error_msg).':'.$error_msg.'e';

$child = 'require '.var_export($settings['functions'].'function.tracker.error.php', true).';'.
	' tracker_error('.var_export($error_msg, true).');';

$output = array();
$status = null;
exec(
	escapeshellarg(PHP_BINARY).' -d error_reporting=0 -d display_errors=0 -r '.escapeshellarg($child).' 2>/dev/null',
	$output,
	$status
);

if (
	implode(PHP_EOL, $output) !== $expected ||
	$status !== 2
) {
	echo 'Error: Test for Function "tracker_error" failed.'.PHP_EOL;
	$failure = true;
}
