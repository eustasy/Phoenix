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

		// TODO Downloaded count.
		$scrape['downloads'] = 0;
		$scrape['peers'] = $scrape['seeders'] + $scrape['leechers'];

		// XML
		if ( isset($_GET['xml']) ) {
			header('Content-Type: text/xml');
			echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
					'<torrent>'.
					'	<info_hash>'.$torrent           .'</info_hash>'.
					'	<seeders>'  .$stats['seeders']  .'</seeders>'.
					'	<leechers>' .$stats['leechers'] .'</leechers>'.
					'	<peers>'    .$scrape['peers']   .'</peers>'.
					'	<downloads>'.$stats['downloads'].'</downloads>'.
					'</torrent>';

		// JSON
		} else if ( isset($_GET['json']) ) {
			header('Content-Type: application/json');
			echo json_encode(
				array(
					'torrent' => array(
						'info_hash' => $torrent,
						'seeders'   => $scrape['seeders'],
						'leechers'  => $scrape['leechers'],
						'peers'     => $scrape['peers'],
						'downloads' => $scrape['downloads'],
					),
				)
			);

		} else {
			echo 'd5:filesd'.strlen($torrent).':'.$torrent.'d8:completei'.$scrape['seeders'].'e10:downloadedi'.$scrape['downloads'].'e10:incompletei'.$scrape['leechers'].'ee';
		}

	}

}