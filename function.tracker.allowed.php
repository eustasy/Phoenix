<?php

function tracker_allowed() {

	global $connection, $settings;

	require_once __DIR__.'/once.db.connect.php';

	require_once __DIR__.'/function.mysqli.array.build.php';
	$torrents = mysqli_array_build('SELECT `info_hash` FROM `'.$settings['db_prefix'].'torrents`');
	if ( !$torrents ) {
		tracker_error('Failed to retrieve allowed torrents.');
	} else {
		return $torrents;
	}

}