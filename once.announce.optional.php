<?php

////	Left
// Optional
// An integer representing the remaining bytes to download.
if ( isset($_GET['left']) ) {
	if (
		is_int($_GET['left']) &&
		$_GET['left'] == 0
	) {
		$_GET['left'] = 0;
		$settings['seeding'] = 1;
	} else {
		$_GET['left'] = $_GET['left'];
		$settings['seeding'] = 0;
	}
} else {
	$_GET['left'] = -1;
	$settings['seeding'] = 0;
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
	$_GET['compact'] = 1;
} else {
	$_GET['compact'] = 0;
}

////	no_peer_id
// Optional
// An integer representing a boolean.
// Defines whether or not to omit peer_id in dictionary announce response.
if (
	(
		isset($_GET['no_peer_id']) &&
		intval($_GET['no_peer_id']) > 0
	)
) {
	$_GET['no_peer_id'] = 1;
} else {
	$_GET['no_peer_id'] = 0;
}

////	numwant
// Optional
// An integer representing the number of peers that the client wants.
if ( !isset($_GET['numwant']) ) {
	$_GET['numwant'] = $settings['default_peers'];
} else if (
	intval($_GET['numwant']) > $settings['max_peers'] ||
	intval($_GET['numwant']) < 1
) {
	$_GET['numwant'] = $settings['max_peers'];
} else {
	$_GET['numwant'] = intval($_GET['numwant']);
}
