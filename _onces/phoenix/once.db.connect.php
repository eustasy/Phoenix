<?php

// IF database is configured
if (
	!empty($settings['db_host']) &&
	!empty($settings['db_user']) &&
	!empty($settings['db_name'])
) {
	// IF persistent connection
	if ( $settings['db_persist']) {
		$settings['db_host'] = 'p:'.$settings['db_host'];
	}

	$connection = mysqli_connect($settings['db_host'], $settings['db_user'], $settings['db_pass'], $settings['db_name']);

	if ( !$connection ) {
		// mysqli_connect_error() takes no arguments; passing the failed
		// connection was a fatal ArgumentCountError on PHP 8. The driver
		// detail names the DB user and host, so it goes to the server log
		// for the operator — clients only get the generic failure.
		error_log('Phoenix: database connection failed: '.mysqli_connect_error());
		tracker_error('Connection Failed. Tracker may be mis-configured.');
	}

} else {
	tracker_error('Connection Failed. Tracker is not configured.');
}
