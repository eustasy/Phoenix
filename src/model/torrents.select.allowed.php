<?php

////	torrents_select_allowed
// Returns the list of permitted info_hashes for closed-tracker mode.
// Returns an empty array (not an error) when no torrents are registered.
function torrents_select_allowed(mysqli $connection, array $settings): array {
	require_once $settings['functions'].'function.mysqli.array.build.php';
	$sql = 'SELECT `info_hash` FROM `'.$settings['db_prefix'].'torrents`;';
	$allowed_torrents = mysqli_array_build($connection, $sql);
	if ( !$allowed_torrents ) {
		return array();
	}
	return $allowed_torrents;
}
