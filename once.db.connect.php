<?php

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
		tracker_error('Connection Failed. Tracker may be mis-configured. '.mysqli_connect_error($connection));
	}

} else {
	tracker_error('Connection Failed. Tracker is not configured.');
}