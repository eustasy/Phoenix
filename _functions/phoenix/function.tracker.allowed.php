<?php

////	tracker_allowed
// Returns the list of permitted info_hashes for closed-tracker mode.
// Returns an empty array (not an error) when no torrents are registered.
function tracker_allowed($connection, $settings) {
	require_once $settings['functions'].'function.mysqli.array.build.php';
	$sql = 'SELECT `info_hash` FROM `'.$settings['db_prefix'].'torrents`;';
	$allowed_torrents = mysqli_array_build($connection, $sql);
	if ( !$allowed_torrents ) {
		// tracker_error('No torrents allowed at this time.');
		return array();
	} else {
		return $allowed_torrents;
	}
}
