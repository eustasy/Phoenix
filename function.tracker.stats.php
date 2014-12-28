<?php

function tracker_stats() {

	global $connection, $settings;

	require_once __DIR__.'/once.db.connect.php';
	require_once __DIR__.'/function.mysqli.fetch.once.php';

	// Statistics
	$stats = mysqli_fetch_once(
		// select seeders and leechers
		'SELECT '.
		'SUM(`state`=\'1\') AS `seeders`, '.
		'SUM(`state`=\'0\') AS `leechers`, '.
		// unique torrents
		'COUNT(DISTINCT info_hash) AS `torrents` '.
		// from peers
		'FROM `'.$settings['db_prefix'].'peers`'
	);

	if ( !$stats ) {
		tracker_error('Unable to get stats.');
	} else {

		$phoenix_version = 'Phoenix Procedural 2 2014-12-27 23:03:00Z eustasy';

		// XML
		if ( isset($_GET['format']) && $_GET['format'] == 'xml' ) {
			header('Content-Type: text/xml');
			echo '<?xml version="1.0" encoding="ISO-8859-1"?>'.
				 '<tracker version="$Id: '.$phoenix_version.' $">'.
				 '<peers>'.number_format($stats['seeders'] + $stats['leechers']).'</peers>'.
				 '<seeders>'.number_format($stats['seeders']).'</seeders>'.
				 '<leechers>'.number_format($stats['leechers']).'</leechers>'.
				 '<torrents>'.number_format($stats['torrents']).'</torrents></tracker>';

		// JSON
		} else if ( isset($_GET['format']) && $_GET['format'] == 'json' ) {
				header('Content-Type: application/json');
				echo '{"tracker":{'.
					'version":"$Id: '.$phoenix_version.' $",'.
					'"peers": "'.number_format($stats['seeders'] + $stats['leechers']).'",'.
					'"seeders":"'.number_format($stats['seeders']).'",'.
					'"leechers":"'.number_format($stats['leechers']).'",'.
					'"torrents":"'.number_format($stats['torrents']).'"}}';

		// HTML
		} else {
				echo '<!DocType html><html><head><meta charset="UTF-8">'.
					 '<title>Phoenix: $Id: '.$phoenix_version.' $</title>'.
					 '<body><pre>'.number_format($stats['seeders'] + $stats['leechers']).
					 ' peers ('.number_format($stats['seeders']).' seeders + '.number_format($stats['leechers']).
					 ' leechers) in '.number_format($stats['torrents']).' torrents</pre></body></html>';
		}

	}

}