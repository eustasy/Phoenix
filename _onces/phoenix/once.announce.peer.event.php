<?php

require_once $settings['functions'].'function.mysqli.fetch.once.php';

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
		if ( $peer ) {
			require_once $settings['functions'].'function.peer.delete.php';
			peer_delete($connection, $settings, $peer);
			// HOOK PEER DELETE
			if ( is_readable($settings['hooks'].'phoenix.peer.delete.php') ) {
				include $settings['hooks'].'phoenix.peer.delete.php';
			}
		}
		// EXIT Only because the client does not require any data.
		exit;
	// END IF Peer Exited

	// IF Peer Completed
	} else if ( $_GET['event'] == 'completed' ) {
		// Force Seeding Status
		$peer['state'] = 1;
		// Increment downloads
		require_once $settings['functions'].'function.peer.completed.php';
		peer_completed($connection, $settings, $peer);
		// HOOK DOWNLOAD COMPLETE
		if ( is_readable($settings['hooks'].'phoenix.download.complete.php') ) {
			include $settings['hooks'].'phoenix.download.complete.php';
		}
	} // END IF Peer Completed

} // END IF Event

// IF Any Change
if (
	// No Existing Peer
	!$peer['old'] ||
	// IP has changed.
	$peer['ipv4'] != $peer['old']['ipv4'] ||
	$peer['ipv6'] != $peer['old']['ipv6'] ||
	// Port has changed.
	$peer['portv4'] != $peer['old']['portv4'] ||
	$peer['portv6'] != $peer['old']['portv6'] ||
	// check whether seeding status match
	$peer['state'] != $peer['old']['state']
) {
	require_once $settings['functions'].'function.peer.new.php';
	peer_new($connection, $settings, $time, $peer);
	// HOOK PEER NEW/CHANGE
	if ( is_readable($settings['hooks'].'phoenix.peer.change.php') ) {
		include $settings['hooks'].'phoenix.peer.change.php';
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
