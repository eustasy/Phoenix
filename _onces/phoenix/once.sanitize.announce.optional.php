<?php

////	Left
// Optional
// An integer representing the remaining bytes to download.
if ( isset($_GET['left']) ) {
	if ( intval($_GET['left']) == 0 ) {
		$peer['left'] = 0;
		$peer['state'] = 1;
	} else {
		$peer['left'] = intval($_GET['left']);
		$peer['state'] = 0;
	}
} else {
	$peer['left'] = -1;
	$peer['state'] = 0;
}

////	compact
// Optional
// An integer representing a boolean.
// Defines whether or not to send a compact peer response.
// http://bittorrent.org/beps/bep_0023.html
if (
	(
		isset($_GET['compact']) &&
		intval($_GET['compact']) > 0
	) ||
	(
		!isset($_GET['compact']) &&
		$settings['default_compact']
	)
) {
	$peer['compact'] = 1;
} else {
	$peer['compact'] = 0;
}

////	no_peer_id
// Optional
// An integer representing a boolean.
// Defines whether or not to omit peer_id in dictionary announce response.
if (
	isset($_GET['no_peer_id']) &&
	intval($_GET['no_peer_id']) > 0
) {
	$peer['no_peer_id'] = 1;
} else {
	$peer['no_peer_id'] = 0;
}

////	numwant
// Optional
// An integer representing the number of peers that the client wants.
if ( !isset($_GET['numwant']) ) {
	$peer['numwant'] = $settings['default_peers'];
} else if (
	intval($_GET['numwant']) > $settings['max_peers'] ||
	intval($_GET['numwant']) < 1
) {
	$peer['numwant'] = $settings['max_peers'];
} else {
	$peer['numwant'] = intval($_GET['numwant']);
}
