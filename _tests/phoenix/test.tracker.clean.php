<?php

require_once $settings['functions'].'function.tracker.clean.php';
$result = tracker_clean($connection, $settings, $time);
if ( !$result ) {
	echo 'Error: Test for Function "task" failed.';
	exit(1);
}
