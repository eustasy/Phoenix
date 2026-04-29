<?php

require_once $settings['functions'].'function.mysqli.fetch.once.php';

// Statistics
$sql = 'SELECT '.
	// select seeders and leechers
	'SUM(`state`=\'1\') AS `seeders`, '.
	'SUM(`state`=\'0\') AS `leechers`, '.
	// unique torrents
	'COUNT(DISTINCT info_hash) AS `torrents` '.
	// from peers
	'FROM `'.$settings['db_prefix'].'peers`;';
$stats = mysqli_fetch_once($connection, $sql);

// Downloads and Traffic
$sql = 'SELECT '.
	'SUM(`downloads`) AS `downloads`, '.
	'SUM(`downloads` * IFNULL(`size`, 0)) AS `traffic` '.
	'FROM `'.$settings['db_prefix'].'torrents`;';
$downloads = mysqli_fetch_once($connection, $sql);

if (
	!$stats ||
	!$downloads
) {
	tracker_error('Unable to get stats.');

} else {
	$stats['seeders']   = intval($stats['seeders']);
	$stats['leechers']  = intval($stats['leechers']);
	$stats['torrents']  = intval($stats['torrents']);
	$stats['downloads'] = intval($downloads['downloads']);
	$stats['traffic']   = intval($downloads['traffic']);
	$stats['peers']     = $stats['seeders'] + $stats['leechers'];

	// XML
	if ( isset($_GET['xml']) ) {
		header('Content-Type: text/xml');
		echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
			 '<tracker version="$Id: '.$settings['phoenix_version'].' $">'.
			 '<peers>'.$stats['peers'].'</peers>'.
			 '<seeders>'.$stats['seeders'].'</seeders>'.
			 '<leechers>'.$stats['leechers'].'</leechers>'.
			 '<torrents>'.$stats['torrents'].'</torrents>'.
			 '<downloads>'.$stats['downloads'].'</downloads>'.
			 '<traffic>'.$stats['traffic'].'</traffic></tracker>';

	// JSON
	} else if ( isset($_GET['json']) ) {
			header('Content-Type: application/json');
			echo json_encode(
				array(
					'tracker' => array(
						'version'   => '$Id: '.$settings['phoenix_version'].' $,',
						'peers'     => $stats['peers'],
						'seeders'   => $stats['seeders'],
						'leechers'  => $stats['leechers'],
						'torrents'  => $stats['torrents'],
						'downloads' => $stats['downloads'],
						'traffic'   => $stats['traffic'],
					),
				)
			);

	// HTML
	} else {
			echo '<!DocType html><html><head><meta charset="UTF-8">'.
				 '<title>Phoenix: $Id: '.$settings['phoenix_version'].' $</title>'.
				 '<body><pre>'.number_format($stats['peers']).
				 ' peers ('.number_format($stats['seeders']).' seeders + '.number_format($stats['leechers']).
				 ' leechers) in '.number_format($stats['torrents']).' torrents and'.
				 ' '.number_format($stats['downloads']).' downloads completed,'.
				 ' '.number_format($stats['traffic']).' bytes served.</pre></body></html>';
	}

}
