<?php

echo 'Starting Test: Tracker Clean';
require_once $settings['functions'].'function.tracker.clean.php';
$result = tracker_clean($connection, $settings, $time);
if ( !$result ) {
	echo 'Error: Test for Function "task" failed.';
	exit(1);
}
echo 'Finished Test: Tracker Clean';
