<?php

// Merge peer counts from the peers query into the scrape array.
while ( $peer = mysqli_fetch_assoc($peers) ) {
	$scrape[$peer['info_hash']]['info_hash'] = $peer['info_hash'];
	$scrape[$peer['info_hash']]['seeders']   = $peer['seeders'];
	$scrape[$peer['info_hash']]['leechers']  = $peer['leechers'];
}

// Merge size and download counts from the torrents query.
while ( $torrent = mysqli_fetch_assoc($torrents) ) {
	$scrape[$torrent['info_hash']]['info_hash'] = $torrent['info_hash'];
	$scrape[$torrent['info_hash']]['size']      = $torrent['size'];
	$scrape[$torrent['info_hash']]['downloads'] = $torrent['downloads'];
}

// intval() guards against NULL from SQL SUM() on an empty set.
foreach ( $scrape as $torrent ) {
	$scrape[$torrent['info_hash']]['info_hash'] = $torrent['info_hash'];
	$scrape[$torrent['info_hash']]['seeders']   = intval($torrent['seeders']);
	$scrape[$torrent['info_hash']]['leechers']  = intval($torrent['leechers']);
	$scrape[$torrent['info_hash']]['peers']     = intval($torrent['seeders']) + intval($torrent['leechers']);
	$scrape[$torrent['info_hash']]['size']      = intval($torrent['size']);
	$scrape[$torrent['info_hash']]['downloads'] = intval($torrent['downloads']);
	$scrape[$torrent['info_hash']]['traffic']   = intval($torrent['size']) * intval($torrent['downloads']);
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
			'<size>'     .$torrent['size']     .'</size>'.
			'<downloads>'.$torrent['downloads'].'</downloads>'.
			'<traffic>'  .$torrent['traffic']  .'</traffic>'.
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
			'size'      => $torrent['size'],
			'downloads' => $torrent['downloads'],
			'traffic'   => $torrent['traffic'],
		);
	}
	echo json_encode($json);

// Bencode (BEP 15 scrape response)
} else {
	$bencode = 'd'.
		'5:files';
	foreach ( $scrape as $torrent ) {
		// BEP 15: the files dict key is the raw 20-byte info_hash, not hex.
		// BEP 15 uses 'complete' (seeders), 'downloaded' (finished downloads), 'incomplete' (leechers).
		$bencode .= 'd'.
			'20:'.hex2bin($torrent['info_hash']).
			'd'.
				'8:complete'  .'i'.$torrent['seeders']  .'e'.
				'10:downloaded'.'i'.$torrent['downloads'].'e'.
				'10:incomplete'.'i'.$torrent['leechers'] .'e'.
			'e'.
		'e';
	}
	echo $bencode.'e';
}
