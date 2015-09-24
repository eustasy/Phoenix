<?php

// Load Phoenix Core
require_once __DIR__.'/phoenix.php';
require_once __DIR__.'/function.annouce.validate.php';

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
	!in_array($_GET['info_hash'], $allowed_torrents)
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
	// Determine if the client is using IPv4 or IPv6

	// If we're honoring X_FORWARDED_FOR, we check and use that first
	// if its present
	if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $settings['honor_xff']) {
		$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else if ( isset($_SERVER['HTTP_CLIENT_IP']) && $settings['honor_xff']) {
		$client_ip = $_SERVER['HTTP_CLIENT_IP'];
	} else if ( isset($_SERVER['REMOTE_ADDR']) ) {
		$client_ip = $_SERVER['REMOTE_ADDR'];
	} else {
		tracker_error('Unable to obtain client IP');
	}

	// Ok, our behavior changes depending if we're being contacted via IPv4 or IPv6

	// filter_var VALIDATE_IP works with raw IPs, which should be what we always get
	// in this case. It will fall over with port encoded IPv6 adddresses

	if (filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		$client_ip_family = 'ipv4';
	} else if (filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		$client_ip_family= 'ipv6';
	} else {
		tracker_error('Unknown IP family!');
	}

	// Handle IP processing depending on our client family
	if ( $client_ip_family === 'ipv6' ) {
		// If we don't get ipv6 explicately, then copy it from
		// client_ip
		if ( !isset($_GET['ipv6']) ) {
			$_GET['ipv6'] = $client_ip;
		}

		validate_ipv6();

		// Handle getting a v4 address ...
		if ( isset($_GET['ipv4']) ) {
			validate_ipv4();
		}
	} else {
		// Connection is via IPv4, but may include v6 information
		// we ignore a ipv4= tag if we're connecting via v4

		if ( !isset($_GET['ip']) || !$settings['external_ip']) {
			$_GET['ip'] = $client_ip;
		}

		// FOr sanity sake, move this to its own variable
		$_GET['ipv4'] = $_GET['ip'];

		// Validate the IP address
		validate_ipv4();

		// if we got a v6 flag, praise it
		if ( isset($_GET['ipv6']) ) {
			validate_ipv6();
		}
	}

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

	// END OPTIONAL ITEMS

	// Track Client
	require_once __DIR__.'/function.peer.event.php';
	peer_event($connection, $settings, $time);

	// Clean Up
	require_once __DIR__.'/function.tracker.clean.php';
	tracker_clean($connection, $settings, $time);

	// Announce Peers
	require_once __DIR__.'/function.torrent.announce.php';
	torrent_announce($connection, $settings);

}
