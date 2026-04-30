<?php

declare(strict_types=1);

////	scrape_render_json
// Renders a normalised $scrape array as the JSON form of a scrape response,
// keyed by info_hash. Caller is responsible for emitting the Content-Type
// header.
function scrape_render_json(array $scrape): string {
	$json = array();
	foreach ( $scrape as $torrent ) {
		$json[$torrent['info_hash']] = array(
			'info_hash' => $torrent['info_hash'],
			'seeders'   => $torrent['seeders'],
			'leechers'  => $torrent['leechers'],
			'peers'     => $torrent['peers'],
			'size'      => $torrent['size'],
			'downloads' => $torrent['downloads'],
			'traffic'   => $torrent['traffic'],
		);
	}
	return json_encode($json);
}
