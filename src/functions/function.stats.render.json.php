<?php

////	stats_render_json
// Render tracker statistics as JSON.
// Outputs JSON and terminates.

function stats_render_json($stats, $settings) {
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
}
