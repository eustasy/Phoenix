<?php

function peer_event() {

	global $connection, $settings;

	require_once __DIR__.'/once.db.connect.php';
	require_once __DIR__.'/function.mysqli.fetch.once.php';

	$peer = mysqli_fetch_once(
		// SELECT the Peer
		'SELECT * FROM `'.$settings['db_prefix'].'peers` '.
		// that matches the given info_hash and peer_id
		'WHERE `info_hash`=\''.$_GET['info_hash'].'\' AND `peer_id`=\''.$_GET['peer_id'].'\';'
	);

	// IF Event
	if ( isset($_GET['event']) ) {
		// IF Peer Exited
		if ( $_GET['event'] == 'stopped' ) {
			if ( $peer ) {
				require_once __DIR__.'/function.peer.delete.php';
				peer_delete();
				// HOOK PEER DELETE
				if ( is_readable(__DIR__.'/hook.peer.delete.php') ) {
					include __DIR__.'/hook.peer.delete.php';
				}
			}
			// EXIT Only because the client does not require any data.
			exit;
		// END IF Peer Exited

		// IF Peer Completed
		} else if ( $_GET['event'] == 'completed' ) {
			// Force Seeding Status
			$settings['seeding'] = 1;
			// Increment downloads
			require_once __DIR__.'/function.peer.completed.php';
			peer_completed();
			// HOOK DOWNLOAD COMPLETE
			if ( is_readable(__DIR__.'/hook.download.complete.php') ) {
				include __DIR__.'/hook.download.complete.php';
			}
		} // END IF Peer Completed

	} // END IF Event

	// IF Any Change
	if (
		// No Existing Peer
		!$peer ||
		// IP has changed.
		$peer['ipv4'] != $_GET['ipv4'] ||
		$peer['ipv6'] != $_GET['ipv6'] ||
		// Port has changed.
		$peer['portv4'] != $_GET['portv4'] ||
		$peer['portv6'] != $_GET['portv6'] ||
		// check whether seeding status match
		$peer['state'] != $settings['seeding']
	) {
		require_once __DIR__.'/function.peer.new.php';
		peer_new();
		// HOOK PEER NEW/CHANGE
		if ( is_readable(__DIR__.'/hook.peer.change.php') ) {
			include __DIR__.'/hook.peer.change.php';
		}
	// END Any Change

	// IF Unchanged
	} else {
		require_once __DIR__.'/function.peer.access.php';
		peer_access();
		// HOOK PEER ACCESS
		if ( is_readable(__DIR__.'/hook.peer.access.php') ) {
			include __DIR__.'/hook.peer.access.php';
		}
	} // END IF Unchanged

}