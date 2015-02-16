<?php

// Load Phoenix Core
require_once __DIR__.'/phoenix.php';

////	info_hash
// Required
// 40 Characters
// Hexadecimal
if (
	!isset($_GET['info_hash']) ||
	strlen($_GET['info_hash']) != 40
) {
	tracker_error('Info Hash is invalid.');

////	IF Allowed
// Tracker is Open
// OR
// Torrent is Allowed
} else if (
	!$settings['open_tracker'] &&
	!in_array($_GET['info_hash'], $torrents)
) {
	tracker_error('Torrent is not allowed.');

////	peer_id
// Required
// 40 Characters
// Hexadecimal
} else if (
	!isset($_GET['peer_id']) ||
	strlen($_GET['peer_id']) != 40
) {
	tracker_error('Peer ID is invalid.');

} else {

	////	IF EXTERNAL IP ONLY
	// IF not ip set
	// OR
	// IF external ips not allowed
	if (
		!isset($_GET['ip']) ||
		!$settings['external_ip']
	) {
		if ( isset($_SERVER['REMOTE_ADDR']) ) {
			$_GET['ip'] =$_SERVER['REMOTE_ADDR'];
			if ( !ip2long($_GET['ip']) ) {
				tracker_error('Invalid IP, dotted decimal only. No IPv6.');
			}
		} else if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
			$_GET['ip'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else if ( isset($_SERVER['HTTP_CLIENT_IP']) ) {
			$_GET['ip'] = $_SERVER['HTTP_CLIENT_IP'];
		} else {
			tracker_error('Could not locate clients IP.');
		}
	} // END IF EXTERNAL IP ONLY

	////	ip
	// Required
	// A String representing the IPv4 Address the peer can be found on.
	$_GET['ip'] = trim($_GET['ip'],'::ffff:');
	if ( strpos($_GET['ip'], ':') !== false ) {
		$_GET['ip'] = explode(':', $_GET['ip']);
		$_GET['port'] = $_GET['ip'][1];
		$_GET['ip'] = $_GET['ip'][0];
	}

	// TODO Add IPv6 Support
	// https://github.com/eustasy/phoenix/issues/3
	if ( !ip2long($_GET['ip']) ) {
		tracker_error('Invalid IP, dotted decimal only. No IPv6.');
	}

	// BEGIN OPTIONAL ITEMS

	////	port
	// Required
	// An integer representing the port the peer is using.
	if (
		// integer - port
		// port the client is accepting connections from
		!isset($_GET['port']) ||
		!is_numeric($_GET['port'])
	) {
		tracker_error('Client listening port is invalid.');
	}
	$_GET['port'] = intval($_GET['port']);

	////	Left
	// Optional
	// An integer representing the remaining bytes to download.
	if ( isset($_GET['left']) ) {
		if (
			is_numeric($_GET['left']) &&
			$_GET['left'] == 0
		) {
			$_GET['left'] = 0;
			$settings['seeding'] = 1;
		} else {
			$_GET['left'] = intval($_GET['left']);
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
	if ( !isset($_GET['no_peer_id']) ) {
		$_GET['no_peer_id'] = 0;
	} else {
		$_GET['no_peer_id'] = intval($_GET['no_peer_id']);
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

	// END OPTIONAL ITEMS

	// Connect
	require_once __DIR__.'/once.db.connect.php';

	// Track Client
	require_once __DIR__.'/function.peer.event.php';
	peer_event();

	// Clean Up
	require_once __DIR__.'/function.tracker.clean.php';
	tracker_clean();

	// Announce Peers
	require_once __DIR__.'/function.torrent.announce.php';
	torrent_announce();

}