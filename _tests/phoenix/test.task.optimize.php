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

// Regression: with no specific table and defaults disabled, $tables is empty.
// The function should short-circuit to true rather than passing an empty SQL
// string to mysqli_multi_query (which would return false).
$result = task_optimize($connection, $settings, $time, false, false);
if ( $result !== true ) {
	echo 'Error: task_optimize(false, false) should return true for an empty table set.'.PHP_EOL;
	$failure = true;
}
