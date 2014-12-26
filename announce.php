<?php

// This file defines all the classes and functions we will need.
// Since it defines things, we need to make sure it isn't loaded twice.
require_once __DIR__.'/phoenix.php';

// IF MAGIC QUOTES
if ( get_magic_quotes_gpc() ) {
	// Strip auto-escaped data.
	$_GET['info_hash'] = stripslashes($_GET['info_hash']);
	$_GET['peer_id'] = stripslashes($_GET['peer_id']);
} // END IF MAGIC QUOTES

if (
	// 20-bytes - info_hash
	// sha-1 hash of torrent metainfo
	!isset($_GET['info_hash']) ||
	strlen($_GET['info_hash']) != 20
) {
	tracker_error('Torrent Hash is invalid.');

// TODO
// Check torrent is allowed when private.

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
	if (
		isset($_GET['left']) &&
		is_numeric($_GET['left'])
	) {
		$_SERVER['tracker']['seeding'] = ($_GET['left'] > 0 ? 0 : 1);
	} else {
		$_SERVER['tracker']['seeding'] = 0;
	}

	// integer boolean - compact - optional
	// send a compact peer response
	// http://bittorrent.org/beps/bep_0023.html
	if (
		!isset($_GET['compact']) ||
		$_SERVER['tracker']['force_compact']
	) {
		$_GET['compact'] = 1;
	} else {
		$_GET['compact'] += 0;
	}

	// integer boolean - no_peer_id - optional
	// omit peer_id in dictionary announce response
	if ( !isset($_GET['no_peer_id']) ) {
		$_GET['no_peer_id'] = 0;
	} else {
		$_GET['no_peer_id'] += 0;
	}

	// string - ip - optional
	// ip address the peer requested to use

	// TODO Add IPv6 Support
	// http://bittorrent.org/beps/bep_0007.html

	if (
		isset($_GET['ip']) &&
		$_SERVER['tracker']['external_ip']
	) {
		// dotted decimal only
		$_GET['ip'] = trim($_GET['ip'],'::ffff:');
		if ( !ip2long($_GET['ip']) ) {
			tracker_error('Invalid IP, dotted decimal only. No IPv6.');
		}
	// set ip to connected client
	} else if ( isset($_SERVER['REMOTE_ADDR']) ) {
		$_GET['ip'] = trim($_SERVER['REMOTE_ADDR'], '::ffff:');
		if ( !ip2long($_SERVER['REMOTE_ADDR']) ) {
			tracker_error('Invalid IP, dotted decimal only. No IPv6.');
		}
	// cannot locate suitable ip, must abort
	} else {
		tracker_error('Could not locate clients IP.');
	}

	// integer - numwant - optional
	// number of peers that the client has requested
	if ( !isset($_GET['numwant']) ) {
		$_GET['numwant'] = $_SERVER['tracker']['default_peers'];
	} else if ( ( $_GET['numwant'] + 0 ) > $_SERVER['tracker']['max_peers'] ) {
		$_GET['numwant'] = $_SERVER['tracker']['max_peers'];
	} else {
		$_GET['numwant'] += 0;
	}

	// Open Database
	phoenix::open();

	// Make info_hash & peer_id SQL friendly
	$_GET['info_hash'] = phoenix::$api->escape_sql($_GET['info_hash']);
	$_GET['peer_id']   = phoenix::$api->escape_sql($_GET['peer_id']);

	// Track Client
	phoenix::event();

	// Clean Up
	phoenix::clean();

	// Announce Peers
	phoenix::peers();

	// Close Database
	phoenix::close();

}