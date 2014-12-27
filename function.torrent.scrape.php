<?php

function torrent_scrape($torrent) {

	global $connection, $settings;

	require_once __DIR__.'/once.db.connect.php';
	require_once __DIR__.'/function.mysqli.fetch.once.php';

	// select seeders and leechers
	$query = 'SELECT '.
	'SUM(`state`=\'1\') AS `seeders`, '.
	'SUM(`state`=\'0\') AS `leechers` '.
	// from peers
	'FROM `'.$settings['db_prefix'].'peers` ';
	if ( strlen($torrent) == 20 ) {
		// Assume BINARY
		$query .= 'WHERE `info_hash`=\''.$torrent.'\'';
	} else {
		// Assume HEX
		$query .= 'WHERE HEX(`info_hash`)=\''.$torrent.'\'';
	}

	$scrape = mysqli_fetch_once($query);

	if ( !$scrape ) {
		tracker_error('Unable to scrape for that torrent.');
	} else {

		// TODO Rewrite to allow JSON or XML output.
		// TODO Downloaded count.
		echo 'd5:filesd'.strlen($torrent).':'.$torrent.'d8:completei'.$scrape['seeders'].'e10:downloadedi0e10:incompletei'.$scrape['leechers'].'ee';

	}

}