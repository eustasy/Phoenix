<?php

// A full scrape (once.scrape.tracker.php) does not pre-initialise $scrape,
// so an empty tracker must still start from an empty array rather than null.
if ( !isset($scrape) ) {
	$scrape = array();
}

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
	// On a full scrape a hash may appear in only one of the two queries;
	// zero-fill the other query's keys before normalising.
	$torrent += array('seeders' => 0, 'leechers' => 0, 'size' => 0, 'downloads' => 0);
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
	// A root element is required; multiple sibling <torrent> nodes are not a document.
	$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><torrents>';
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
	echo $xml.'</torrents>';

// JSON
} else if ( isset($_GET['json']) ) {
	header('Content-Type: application/json');
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
	// Cast so an empty result set encodes as {} rather than [].
	echo json_encode((object) $json);

// Bencode (BEP 15 scrape response)
} else {
	// Bencode requires dict keys in sorted raw-byte order; sorting the lowercase
	// hex keys as strings is equivalent (SORT_STRING guards against PHP comparing
	// all-digit or 1e… hex strings numerically).
	ksort($scrape, SORT_STRING);
	// All torrents live in one 'files' dict: d5:filesd<hash1><stats1><hash2><stats2>…ee.
	// Wrapping each entry in its own d…e made every response with more or fewer
	// than exactly one torrent malformed.
	$bencode = 'd'.
		'5:files'.
		'd';
	foreach ( $scrape as $torrent ) {
		// BEP 15: the files dict key is the raw 20-byte info_hash, not hex.
		// BEP 15 uses 'complete' (seeders), 'downloaded' (finished downloads), 'incomplete' (leechers).
		$bencode .=
			'20:'.hex2bin($torrent['info_hash']).
			'd'.
				'8:complete'  .'i'.$torrent['seeders']  .'e'.
				'10:downloaded'.'i'.$torrent['downloads'].'e'.
				'10:incomplete'.'i'.$torrent['leechers'] .'e'.
			'e';
	}
	echo $bencode.'ee';
}
