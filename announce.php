<?php

// This file defines all the classes and functions we will need.
// Since it defines things, we need to make sure it isn't loaded twice.
require_once __DIR__.'/phoenix.php';

// IF MAGIC QUOTES
if ( get_magic_quotes_gpc() ) {
	// Strip auto-escaped data.
	if ( isset($_GET['info_hash']) ) {
		$_GET['info_hash'] = stripslashes($_GET['info_hash']);
	}
	if ( isset($_GET['peer_id']) ) {
		$_GET['peer_id'] = stripslashes($_GET['peer_id']);
	}
} // END IF MAGIC QUOTES

if (
	// 20-bytes - info_hash
	// sha-1 hash of torrent metainfo
	!isset($_GET['info_hash']) ||
	(
		strlen($_GET['info_hash']) != 20 &&
		strlen($_GET['info_hash']) != 40
	)
) {
	tracker_error('Torrent Hash is invalid.');

// Check torrent is allowed when private.
} else if (
	!$settings['open_tracker'] &&
	!in_array(bin2hex($_GET['info_hash']), $torrents) &&
	!in_array($_GET['info_hash'], $torrents)
) {
	tracker_error('Torrent is not allowed.');

} else if (
	// 20-bytes - peer_id
	// client generated unique peer identifier
	!isset($_GET['peer_id']) ||
	strlen($_GET['peer_id']) != 20
) {
	tracker_error('Peer ID is invalid.');

} else if (
	// integer - port
	// port the client is accepting connections from
	!isset($_GET['port']) ||
	!is_numeric($_GET['port'])
) {
	tracker_error('Client listening port is invalid.');

} else {

	// integer - left
	// number of bytes left for the peer to download
	if ( isset($_GET['left']) ) {
		if (
			is_numeric($_GET['left']) &&
			$_GET['left'] == 0
		) {
			$settings['seeding'] = 1;
		} else {
			$settings['seeding'] = 0;
		}
	} else {
		$_GET['left'] = -1;
		$settings['seeding'] = 0;
	}

	// integer boolean - compact - optional
	// send a compact peer response
	// http://bittorrent.org/beps/bep_0023.html
	if (
		(
			isset($_GET['compact']) &&
			intval($_GET['compact']) > 0
		) ||
		$settings['force_compact']
	) {
		$_GET['compact'] = 1;
	} else {
		$_GET['compact'] = 0;
	}

	// integer boolean - no_peer_id - optional
	// omit peer_id in dictionary announce response
	if ( !isset($_GET['no_peer_id']) ) {
		$_GET['no_peer_id'] = 0;
	} else {
		$_GET['no_peer_id'] = intval($_GET['no_peer_id']);
	}

	// string - ip - optional
	// ip address the peer requested to use

	// TODO Add IPv6 Support
	// http://bittorrent.org/beps/bep_0007.html

	if (
		isset($_GET['ip']) &&
		$settings['external_ip']
	) {
		// dotted decimal only
		$_GET['ip'] = trim($_GET['ip'],'::ffff:');
		if ( !ip2long($_GET['ip']) ) {
			tracker_error('Invalid IP, dotted decimal only. No IPv6.');
		}
	// set ip to connected client
	} else if ( isset($_SERVER['REMOTE_ADDR']) ) {
		$_GET['ip'] = trim($_SERVER['REMOTE_ADDR'], '::ffff:');
		if ( !ip2long($_GET['ip']) ) {
			tracker_error('Invalid IP, dotted decimal only. No IPv6.');
		}
	// cannot locate suitable ip, must abort
	} else {
		tracker_error('Could not locate clients IP.');
	}

	// integer - numwant - optional
	// number of peers that the client has requested
	if ( !isset($_GET['numwant']) ) {
		$_GET['numwant'] = $settings['default_peers'];
	} else if ( intval($_GET['numwant']) > $settings['max_peers'] ) {
		$_GET['numwant'] = $settings['max_peers'];
	} else {
		$_GET['numwant'] = intval($_GET['numwant']);
	}

	// Make info_hash & peer_id SQL friendly
	require_once __DIR__.'/once.db.connect.php';
	$_GET['info_hash'] = mysqli_real_escape_string($connection, $_GET['info_hash']);
	$_GET['peer_id']   = mysqli_real_escape_string($connection, $_GET['peer_id']);

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