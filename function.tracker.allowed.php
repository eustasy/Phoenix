<?php

function tracker_allowed($connection, $settings) {
	require_once __DIR__.'/function.mysqli.array.build.php';
	$sql = 'SELECT `info_hash` FROM `'.$settings['db_prefix'].'torrents`;';
	$allowed_torrents = mysqli_array_build($connection, $sql);
	if ( !$allowed_torrents ) {
		tracker_error('No torrents allowed at this time.');
	} else {
		return $allowed_torrents;
	}
}
