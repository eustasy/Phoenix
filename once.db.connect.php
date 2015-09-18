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
		tracker_error('Connection Failed. Tracker may be mis-configured. '.mysqli_connect_error($connection));
	}

	// SQL injection protection
	$_GET['info_hash'] = mysqli_real_escape_string($connection, $_GET['info_hash']);
	$_GET['peer_id']   = mysqli_real_escape_string($connection, $_GET['peer_id']);

} else {
	tracker_error('Connection Failed. Tracker is not configured.');
}