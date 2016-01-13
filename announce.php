<?php

// Load Phoenix Core
require_once __DIR__.'/phoenix.php';
require_once __DIR__.'/once.sanatize.tracker.php';

////	IF info_hash Valid
// Required
// 40 Characters
// Hexadecimal
if ( strlen($peer['info_hash']) != 40 ) {
	tracker_error('Info Hash is invalid.');

////	IF Allowed
// Tracker is not Open
// AND
// Torrent is not Allowed
} else if (
	!$settings['open_tracker'] &&
	!in_array($peer['info_hash'], $allowed_torrents)
) {
	tracker_error('Torrent is not allowed.');

////	peer_id
// Required
// 40 Characters
// Hexadecimal
} else if ( strlen($peer['peer_id'] ) != 40) {
	tracker_error('Peer ID is invalid.');

} else {
	// IP Addresses & Port
	require_once __DIR__.'/once.sanatize.announce.address.php';
	if (
		(
			!$peer['ipv4'] &&
			!$peer['portv4']
		) ||
		(
			!$peer['ipv6'] &&
			!$peer['portv6']
		)
	) {
		tracker_error('Unable to get IP and Port');
	}

	// Optional Items
	require_once __DIR__.'/once.sanatize.announce.optional.php';

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
