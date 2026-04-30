<?php

declare(strict_types=1);

////	scrape_render_bencode
// Renders a normalised $scrape array as the bencode form of a scrape response,
// per BEP 15. The files-dict key is the raw 20-byte info_hash (hex2bin), not
// hex. BEP 15 uses 'complete' (seeders), 'downloaded' (finished downloads),
// 'incomplete' (leechers).
function scrape_render_bencode(array $scrape): string {
	$bencode = 'd5:files';
	foreach ( $scrape as $torrent ) {
		$bencode .= 'd'.
			'20:'.hex2bin($torrent['info_hash']).
			'd'.
				'8:complete'.'i'.$torrent['seeders']  .'e'.
				'10:downloaded'.'i'.$torrent['downloads'].'e'.
				'10:incomplete'.'i'.$torrent['leechers'] .'e'.
			'e'.
		'e';
	}
	return $bencode.'e';
}
