<?php

////	stats_fetch_peer_counts
// Fetch peer statistics from the database.
// Returns array with seeders, leechers, and torrent count, or false on failure.

function stats_fetch_peer_counts($connection, $settings) {
	require_once $settings['functions'].'function.mysqli.fetch.once.php';

	$sql = 'SELECT '.
		// select seeders and leechers
		'SUM(`state`=\'1\') AS `seeders`, '.
		'SUM(`state`=\'0\') AS `leechers`, '.
		// unique torrents
		'COUNT(DISTINCT info_hash) AS `torrents` '.
		// from peers
		'FROM `'.$settings['db_prefix'].'peers`;';
	return mysqli_fetch_once($connection, $sql);
}
