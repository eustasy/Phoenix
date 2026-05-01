<?php

declare(strict_types=1);

////	view_scrape_bencode
// Renders a normalized $scrape array as a bencode scrape response (BEP 15).
// The files-dict key is the raw 20-byte info_hash (hex2bin), not hex.
// BEP 15 uses 'complete' (seeders), 'downloaded' (finished downloads),
// 'incomplete' (leechers).
//
// Arguments:
//   $scrape: array of torrents indexed by info_hash (40-char hex), each with:
//            - info_hash: string (40-char hex)
//            - seeders: int
//            - leechers: int
//            - downloads: int
//
// Returns: bencoded scrape response string.
function view_scrape_bencode(array $scrape): string {
	$bencode = 'd5:filesd';
	foreach ( $scrape as $torrent ) {
		$bencode .= '20:'.hex2bin($torrent['info_hash']).
			'd'.
				'8:completei'.$torrent['seeders']  .'e'.
				'10:downloadedi'.$torrent['downloads'].'e'.
				'10:incompletei'.$torrent['leechers'] .'e'.
			'e';
	}
	return $bencode.'ee';
}
