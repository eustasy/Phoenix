<?php

require_once $settings['functions'].'function.task.optimize.php';

$result = task_optimize($connection, $settings, $time);

////	WARNING
// This test doesn't clean up after itself
// as the only way to test it is to actually
// run a clean on the tracker.

if ( !$result ) {
	echo 'Error: Test for Function "task_optimize" failed.'.PHP_EOL;
	$failure = true;
}
