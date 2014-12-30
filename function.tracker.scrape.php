<?php

function tracker_scrape() {

	global $connection, $settings;

	require_once __DIR__.'/once.db.connect.php';

	$tracker = mysqli_query(
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

	if ( !$tracker ) {
		tracker_error('Unable to scrape the tracker.');
	} else {

		// XML
		if ( isset($_GET['xml']) ) {
			header('Content-Type: text/xml');
			echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
					'<tracker>';
			while ( $scrape = mysqli_fetch_assoc($tracker) ) {
				// TODO Downloaded count.
				$scrape['downloads'] = 0;
				$scrape['peers'] = $scrape['seeders'] + $scrape['leechers'];
				echo '<torrent>'.
							'<info_hash>'.bin2hex($scrape['torrent']).'</info_hash>'.
							'<seeders>'  .$scrape['seeders']  .'</seeders>'.
							'<leechers>' .$scrape['leechers'] .'</leechers>'.
							'<peers>'    .$scrape['peers']    .'</peers>'.
							'<downloads>'.$scrape['downloads'].'</downloads>'.
						'</torrent>';
			}
			echo '</tracker>';

		// JSON
		} else if ( isset($_GET['json']) ) {
			header('Content-Type: application/json');
			$json = array();
			while ( $scrape = mysqli_fetch_assoc($tracker) ) {
				// TODO Downloaded count.
				$scrape['downloads'] = 0;
				$scrape['peers'] = $scrape['seeders'] + $scrape['leechers'];
				$json[bin2hex($scrape['torrent'])] = array(
					'seeders'   => $scrape['seeders'],
					'leechers'  => $scrape['leechers'],
					'peers'     => $scrape['peers'],
					'downloads' => $scrape['downloads'],
				);
			}
			echo json_encode($json);

		} else {
			$response = 'd5:filesd';
			while ( $scrape = mysqli_fetch_assoc($tracker) ) {
				// TODO Downloaded count.
				$scrape['downloads'] = 0;
				$response .= '20:'.$scrape['torrent'].'d8:completei'.$scrape['seeders'].'e10:downloadedi'.$scrape['downloads'].'e10:incompletei'.$scrape['leechers'].'ee';
			}
			echo $response.'ee';
		}

	}

}