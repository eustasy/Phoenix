<?php

function torrent_scrape() {

	global $connection, $settings;

	require_once __DIR__.'/once.db.connect.php';
	require_once __DIR__.'/function.mysqli.fetch.once.php';

	// select seeders and leechers
	$query = '
		SELECT
			`info_hash`,
			SUM(`state`=\'1\') AS `seeders`,
			SUM(`state`=\'0\') AS `leechers`
		FROM `'.$settings['db_prefix'].'peers`
		WHERE `info_hash`=\''.$_GET['info_hash'].'\';';
	$scrape = mysqli_fetch_once($query);

	if ( !$scrape ) {
		tracker_error('Unable to scrape for that torrent.');
	} else {

		// TODO Downloaded count.
		$scrape['downloads'] = 0;
		$scrape['seeders'] = intval($scrape['seeders']);
		$scrape['leechers'] = intval($scrape['leechers']);
		$scrape['downloads'] = intval($scrape['downloads']);
		$scrape['peers'] = $scrape['seeders'] + $scrape['leechers'];

		// XML
		if ( isset($_GET['xml']) ) {
			header('Content-Type: text/xml');
			echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
					'<torrent>'.
						'<info_hash>'.$_GET['info_hash']  .'</info_hash>'.
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
						'info_hash' => $_GET['info_hash'],
						'seeders'   => $scrape['seeders'],
						'leechers'  => $scrape['leechers'],
						'peers'     => $scrape['peers'],
						'downloads' => $scrape['downloads'],
					),
				)
			);

		} else {
			$echo = 'd
	5:files
	d
		20:'.hex2bin($_GET['info_hash']).'
		d
			8:complete
			i'.$scrape['seeders'].'e
			10:downloaded
			i'.$scrape['downloads'].'e
			10:incomplete
			i'.$scrape['leechers'].'e
		e
	e
e';
			if ( isset($_GET['verbose']) ) {
				echo $echo;
			} else {
				echo preg_replace('/\s+/', '', $echo);
			}
		}

	}

}