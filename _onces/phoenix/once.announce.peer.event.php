<?php

require_once $settings['functions'].'function.mysqli.fetch.once.php';
require_once $settings['functions'].'function.peer.changed.php';

$peer['old'] = mysqli_fetch_once(
	$connection,
	// SELECT the Peer
	'SELECT * FROM `'.$settings['db_prefix'].'peers` '.
	// that matches the given info_hash and peer_id
	'WHERE `info_hash`=\''.$peer['info_hash'].'\' AND `peer_id`=\''.$peer['peer_id'].'\';'
);

// IF Event
if ( isset($_GET['event']) ) {
	// IF Peer Exited
	if ( $_GET['event'] == 'stopped' ) {
		if ( $peer['old'] ) {
			require_once $settings['functions'].'function.peer.delete.php';
			peer_delete($connection, $settings, $peer);
			// HOOK PEER STOPPED
			if ( is_readable($settings['hooks'].'phoenix.peer.stopped.php') ) {
				include $settings['hooks'].'phoenix.peer.stopped.php';
			}
		}
		// EXIT Only because the client does not require any data.
		exit;
	// END IF Peer Exited

	// IF Peer Completed
	} else if ( $_GET['event'] == 'completed' ) {
		// Force Seeding Status
		$peer['state'] = 1;
		// Increment downloads, unless this peer is already recorded as
		// seeding: some clients (e.g. Transmission) send the same announce
		// twice, and the first copy already counted the download when it
		// set state to 1. A completed event from an untracked peer still
		// counts — the swarm may simply have forgotten the row.
		if (
			!$peer['old'] ||
			intval($peer['old']['state']) != 1
		) {
			require_once $settings['functions'].'function.peer.completed.php';
			peer_completed($connection, $settings, $peer);
			// HOOK DOWNLOAD COMPLETE
			if ( is_readable($settings['hooks'].'phoenix.download.complete.php') ) {
				include $settings['hooks'].'phoenix.download.complete.php';
			}
		}
	} // END IF Peer Completed

} // END IF Event

// IF Any Change
if ( peer_changed($peer, $peer['old']) ) {
	require_once $settings['functions'].'function.peer.new.php';
	peer_new($connection, $settings, $time, $peer);
	// HOOK PEER NEW/CHANGE
	if ( $peer['old'] ) {
		if ( is_readable($settings['hooks'].'phoenix.peer.change.php') ) {
			include $settings['hooks'].'phoenix.peer.change.php';
		}
	} else {
		if ( is_readable($settings['hooks'].'phoenix.peer.new.php') ) {
			include $settings['hooks'].'phoenix.peer.new.php';
		}
	}
// END Any Change

// IF Unchanged
} else {
	require_once $settings['functions'].'function.peer.access.php';
	peer_access($connection, $settings, $time, $peer);
	// HOOK PEER ACCESS
	if ( is_readable($settings['hooks'].'phoenix.peer.access.php') ) {
		include $settings['hooks'].'phoenix.peer.access.php';
	}
} // END IF Unchanged
