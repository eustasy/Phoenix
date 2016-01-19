<?php

while ( $torrent = mysqli_fetch_assoc($torrents) ) {
	$scrape[$torrent['info_hash']]['info_hash'] = intval($torrent['info_hash']);
	$scrape[$torrent['info_hash']]['seeders']   = intval($torrent['seeders']);
	$scrape[$torrent['info_hash']]['leechers']  = intval($torrent['leechers']);
	$scrape[$torrent['info_hash']]['downloads'] = intval($torrent['downloads']);
	$scrape[$torrent['info_hash']]['peers']     = intval($torrent['seeders']) + intval($torrent['leechers']);
}

// XML
if ( isset($_GET['xml']) ) {
	header('Content-Type: text/xml');
	$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
	foreach ( $scrape as $torrent ) {
		$xml .= '<torrent>'.
			'<info_hash>'.$torrent['info_hash'].'</info_hash>'.
			'<seeders>'  .$torrent['seeders']  .'</seeders>'.
			'<leechers>' .$torrent['leechers'] .'</leechers>'.
			'<peers>'    .$torrent['peers']    .'</peers>'.
			'<downloads>'.$torrent['downloads'].'</downloads>'.
		'</torrent>';
	}
	echo $xml;

// JSON
} else if ( isset($_GET['json']) ) {
	header('Content-Type: application/json');
	foreach ( $scrape as $torrent ) {
		$json[$torrent['info_hash']] = array(
			'info_hash' => $torrent['info_hash'],
			'seeders'   => $torrent['seeders'],
			'leechers'  => $torrent['leechers'],
			'peers'     => $torrent['peers'],
			'downloads' => $torrent['downloads'],
		);
	}
	echo json_encode($json);

} else {
	$bencode = 'd'.
		'5:files';
	foreach ( $scrape as $torrent ) {
		$bencode .= 'd'.
			'20:'.hex2bin($torrent['info_hash']).
			'd'.
				'8:complete'.'i'.$torrent['seeders'].'e'.
				'10:downloaded'.'i'.$torrent['downloads'].'e'.
				'10:incomplete'.'i'.$torrent['leechers'].'e'.
			'e'.
		'e';
	}
	echo $bencode.'e';
}
