<?php

declare(strict_types=1);

////	index_render_xml
// Renders a normalised $index array as the XML form of a torrent index response.
// Caller is responsible for emitting the Content-Type header.
function index_render_xml(array $index): string {
	$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><torrents>';
	foreach ( $index as $torrent ) {
		$xml .= '<torrent>'.
			'<info_hash>'.$torrent['info_hash'].'</info_hash>'.
			'<name>'.htmlspecialchars($torrent['name']).'</name>'.
			'<size>'.$torrent['size'].'</size>'.
			'<downloads>'.$torrent['downloads'].'</downloads>'.
			'<seeders>'.$torrent['seeders'].'</seeders>'.
			'<leechers>'.$torrent['leechers'].'</leechers>'.
			'<peers>'.$torrent['peers'].'</peers>'.
			'<traffic>'.$torrent['traffic'].'</traffic>'.
		'</torrent>';
	}
	return $xml.'</torrents>';
}
