<?php

function torrent_scrape($connection, $settings, $peer) {

	require_once __DIR__.'/function.mysqli.fetch.once.php';

	// select seeders and leechers
	$sql = '
		SELECT
			`p`.`info_hash` AS `info_hash`,
			SUM(`p`.`state`=\'1\') AS `seeders`,
			SUM(`p`.`state`=\'0\') AS `leechers`,
			`t`.`downloads` AS `downloads`
		FROM `'.$settings['db_prefix'].'peers` AS `p`
			LEFT JOIN `'.$settings['db_prefix'].'torrents` AS `t`
			ON `p`.`info_hash`=`t`.`info_hash`
		WHERE ';

	foreach ( $peer['info_hashes'] as $count => $info_hash ) {
		if ( $count > 0 ) {
			$sql .= ' OR';
		}
		$sql .= ' `p`.`info_hash`=\''.$info_hash.'\'';
	}
	$sql .= ';';

	$scrape = mysqli_fetch_once($connection, $sql);

	if ( !$scrape ) {
		tracker_error('Unable to scrape for that torrent.');

	} else {
		$scrape['seeders'] = intval($scrape['seeders']);
		$scrape['leechers'] = intval($scrape['leechers']);
		$scrape['downloads'] = intval($scrape['downloads']);
		$scrape['peers'] = $scrape['seeders'] + $scrape['leechers'];

		// XML
		if ( isset($_GET['xml']) ) {
			header('Content-Type: text/xml');
			echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
					'<torrent>'.
						'<info_hash>'.$peer['info_hash']  .'</info_hash>'.
						'<seeders>'  .$scrape['seeders']  .'</seeders>'.
						'<leechers>' .$scrape['leechers'] .'</leechers>'.
						'<peers>'    .$scrape['peers']    .'</peers>'.
						'<downloads>'.$scrape['downloads'].'</downloads>'.
					'</torrent>';

		// JSON
		} else if ( isset($_GET['json']) ) {
			header('Content-Type: application/json');
			echo json_encode(
				array(
					'torrent' => array(
						'info_hash' => $peer['info_hash'],
						'seeders'   => $scrape['seeders'],
						'leechers'  => $scrape['leechers'],
						'peers'     => $scrape['peers'],
						'downloads' => $scrape['downloads'],
					),
				)
			);

		} else {
			echo 'd5:files'.
				'd20:'.hex2bin($peer['info_hash']).
				'd8:completei'.$scrape['seeders'].
				'e10:downloadedi'.$scrape['downloads'].
				'e10:incompletei'.$scrape['leechers'].
				'eeee';
		}

	}

}
