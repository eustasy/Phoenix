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

	$results = mysqli_query($connection, $sql);

	if ( !$results ) {
		tracker_error('Unable to scrape for that torrent.');

	} else {
		while ( $torrent = mysqli_fetch_assoc($results) ) {
			$scrape[$torrent['info_hash']]['info_hash'] = intval($torrent['info_hash']);
			$scrape[$torrent['info_hash']]['seeders']   = intval($torrent['seeders']);
			$scrape[$torrent['info_hash']]['leechers']  = intval($torrent['leechers']);
			$scrape[$torrent['info_hash']]['downloads'] = intval($torrent['downloads']);
			$scrape[$torrent['info_hash']]['peers']     = intval($torrent['seeders']) + intval($torrent['leechers']);
		}

		// XML
		if ( isset($_GET['xml']) ) {
			header('Content-Type: text/xml');
			$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
			foreach ( $scrape as $torrent ) {
				$xml .= '<torrent>'.
					'<info_hash>'.$torrent['info_hash'].'</info_hash>'.
					'<seeders>'  .$torrent['seeders']  .'</seeders>'.
					'<leechers>' .$torrent['leechers'] .'</leechers>'.
					'<peers>'    .$torrent['peers']    .'</peers>'.
					'<downloads>'.$torrent['downloads'].'</downloads>'.
				'</torrent>';
			}
			echo $xml;

		// JSON
		} else if ( isset($_GET['json']) ) {
			header('Content-Type: application/json');
			foreach ( $scrape as $torrent ) {
				$json[$torrent['info_hash']] = array(
					'info_hash' => $torrent['info_hash'],
					'seeders'   => $torrent['seeders'],
					'leechers'  => $torrent['leechers'],
					'peers'     => $torrent['peers'],
					'downloads' => $torrent['downloads'],
				);
			}
			echo json_encode($json);

		} else {
			$bencode = 'd'.
				'5:files';
			foreach ( $scrape as $torrent ) {
				$bencode .= 'd'.
					'20:'.hex2bin($torrent['info_hash']).
					'd'.
						'8:complete'.'i'.$torrent['seeders'].'e'.
						'10:downloaded'.'i'.$torrent['downloads'].'e'.
						'10:incomplete'.'i'.$torrent['leechers'].'e'.
					'e'.
				'e';
			}
			echo $bencode.'e';
		}

	}

}
