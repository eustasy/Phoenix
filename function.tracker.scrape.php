<?php

function tracker_scrape() {

	global $connection, $settings;

	require_once __DIR__.'/once.db.connect.php';

	$scrape = mysqli_query(
		$connection,
		// select info_hash, total seeders and leechers
		'SELECT '.
		'`info_hash` AS `torrent`, '.
		'SUM(`state`=\'1\') AS `seeders`, '.
		'SUM(`state`=\'0\') AS `leechers` '.
		// from peers
		'FROM `'.$settings['db_prefix'].'peers` '.
		// grouped by info_hash
		'GROUP BY `info_hash`'
	);

	if ( !$scrape ) {
		tracker_error('Unable to scrape the tracker.');
	} else {

		// TODO Rewrite to arrays and then loop through them to allow JSON or XML output.
		// TODO Downloaded count.
		$response = 'd5:filesd';
		while ( $data = mysqli_fetch_assoc($scrape) ) {
			$response .= '20:'.$data['torrent'].'d8:completei'.$data['seeders'].'e10:downloadedi0e10:incompletei'.$data['leechers'].'ee';
		}
		echo $response.'ee';

	}

}