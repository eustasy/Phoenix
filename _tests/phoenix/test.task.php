<?php
require_once __DIR__.'/../../_phoenix.php';
require_once $settings['functions'].'function.task.php';
$result = task($connection, $settings, '__TASK__', 1);
if ( !$result ) {
	echo 'Error: Test for Function "task" failed.';
	exit(1);
}
