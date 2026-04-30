<?php

declare(strict_types=1);

////	scrape_render_xml
// Renders a normalised $scrape array as the XML form of a scrape response.
// Caller is responsible for emitting the Content-Type header.
function scrape_render_xml(array $scrape): string {
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
