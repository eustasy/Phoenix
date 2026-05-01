<?php

declare(strict_types=1);

////	view_scrape_xml
// Renders a normalized $scrape array as XML scrape response.
// Caller is responsible for emitting the Content-Type header.
//
// Arguments:
//   $scrape: array of torrents indexed by info_hash (40-char hex), each with:
//            - info_hash: string (40-char hex)
//            - seeders: int
//            - leechers: int
//            - peers: int
//            - size: int
//            - downloads: int
//            - traffic: int
//
// Returns: XML string.
function view_scrape_xml(array $scrape): string {
	$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
	foreach ( $scrape as $torrent ) {
		$xml .= '<torrent>'.
			'<info_hash>'.$torrent['info_hash'].'</info_hash>'.
			'<seeders>'  .$torrent['seeders']  .'</seeders>'.
			'<leechers>' .$torrent['leechers'] .'</leechers>'.
			'<peers>'    .$torrent['peers']    .'</peers>'.
			'<size>'     .$torrent['size']     .'</size>'.
			'<downloads>'.$torrent['downloads'].'</downloads>'.
			'<traffic>'  .$torrent['traffic']  .'</traffic>'.
		'</torrent>';
	}
	return $xml;
}
