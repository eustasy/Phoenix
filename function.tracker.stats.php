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

		$phoenix_version = 'Phoenix Procedural 1.3 2015-02-16 20:44:00Z eustasy';

		$stats['seeders'] = intval($stats['seeders']);
		$stats['leechers'] = intval($stats['leechers']);
		$stats['torrents'] = intval($stats['torrents']);
		// TODO Downloads (actual and in output)
		$stats['downloads'] = 0;
		// $stats['downloads'] = intval($stats['downloads']);
		$stats['peers'] = $stats['seeders']+$stats['leechers'];

		// XML
		if ( isset($_GET['xml']) ) {
			header('Content-Type: text/xml');
			echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
				 '<tracker version="$Id: '.$phoenix_version.' $">'.
				 '<peers>'.$stats['peers'].'</peers>'.
				 '<seeders>'.$stats['seeders'].'</seeders>'.
				 '<leechers>'.$stats['leechers'].'</leechers>'.
				 '<torrents>'.$stats['torrents'].'</torrents></tracker>';

		// JSON
		} else if ( isset($_GET['json']) ) {
				header('Content-Type: application/json');
				echo json_encode(
					array(
						'tracker' => array(
							'version' => '$Id: '.$phoenix_version.' $,',
							'peers' => $stats['peers'],
							'seeders' => $stats['seeders'],
							'leechers' => $stats['leechers'],
							'torrents' => $stats['torrents'],
						),
					)
				);

		// HTML
		} else {
				echo '<!DocType html><html><head><meta charset="UTF-8">'.
					 '<title>Phoenix: $Id: '.$phoenix_version.' $</title>'.
					 '<body><pre>'.number_format($stats['peers']).
					 ' peers ('.number_format($stats['seeders']).' seeders + '.number_format($stats['leechers']).
					 ' leechers) in '.number_format($stats['torrents']).' torrents</pre></body></html>';
		}

	}

}