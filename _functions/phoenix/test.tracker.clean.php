<?php

require_once __DIR__.'/../../_phoenix.php';
require_once $settings['functions'].'function.tracker.clean.php';
$result = tracker_clean($connection, $settings, $time);
if ( !$result ) {
	exit('Error #'.mysqli_errno($connection).': "'.mysqli_error($connection).'"');
}
