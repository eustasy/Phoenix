<?php

function tracker_scrape($connection, $settings) {

	$tracker_scrape = mysqli_query(
		$connection,
		// select info_hash, total seeders and leechers
		'SELECT '.
		'`p`.`info_hash` AS `info_hash`, '.
		'SUM(`p`.`state`=\'1\') AS `seeders`, '.
		'SUM(`p`.`state`=\'0\') AS `leechers`, '.
		'`t`.`downloads` AS `downloads` '.
		// from peers
		'FROM `'.$settings['db_prefix'].'peers` AS `p` '.
		'LEFT JOIN `'.$settings['db_prefix'].'torrents` AS `t` '.
		'ON `p`.`info_hash`=`t`.`info_hash` '.
		// grouped by info_hash
		'GROUP BY `info_hash`;'
	);

	if ( !$tracker_scrape ) {
		tracker_error('Unable to scrape the tracker.');
	} else {
		// XML
		if ( isset($_GET['xml']) ) {
			header('Content-Type: text/xml');
			echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
					'<tracker>';
			while ( $scrape = mysqli_fetch_assoc($tracker_scrape) ) {
				$scrape['peers'] = $scrape['seeders'] + $scrape['leechers'];
				echo '<torrent>'.
							'<info_hash>'.$scrape['info_hash']        .'</info_hash>'.
							'<seeders>'  .intval($scrape['seeders'])  .'</seeders>'.
							'<leechers>' .intval($scrape['leechers']) .'</leechers>'.
							'<peers>'    .intval($scrape['peers'])    .'</peers>'.
							'<downloads>'.intval($scrape['downloads']).'</downloads>'.
						'</torrent>';
			}
			echo '</tracker>';

		// JSON
		} else if ( isset($_GET['json']) ) {
			header('Content-Type: application/json');
			$json = array();
			while ( $scrape = mysqli_fetch_assoc($tracker_scrape) ) {
				$scrape['peers'] = $scrape['seeders'] + $scrape['leechers'];
				$json[$scrape['info_hash']] = array(
					'seeders'   => intval( $scrape['seeders']),
					'leechers'  => intval( $scrape['leechers']),
					'peers'     => intval( $scrape['peers']),
					'downloads' => intval( $scrape['downloads']),
				);
			}
			echo json_encode($json);

		// Bencode
		} else {
			$response = 'd'.
				'5:files';
			while ( $scrape = mysqli_fetch_assoc($tracker_scrape) ) {
				$response .= 'd'.
					'20:'.hex2bin($scrape['info_hash']).
					'd'.
						'8:complete'.'i'.intval($scrape['seeders']).'e'.
						'10:downloaded'.'i'.intval($scrape['downloads']).'e'.
						'10:incomplete'.'i'.intval($scrape['leechers']).'e'.
					'e'.
				'e';
			}
			echo $response.'e';
		}

	}

}
