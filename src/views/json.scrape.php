<?php

////	view_scrape_json

//	Returns scrape results as a JSON-encoded string.
//	Input: $scrape array of torrent arrays, each with keys:
//	       info_hash, seeders, leechers, peers, size, downloads, traffic.
//	Output: JSON string with torrents indexed by info_hash.

function view_scrape_json($scrape) {
	$json = array();
	foreach ($scrape as $torrent) {
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
