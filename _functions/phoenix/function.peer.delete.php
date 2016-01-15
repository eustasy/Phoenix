<?php

function peer_delete($connection, $settings, $peer) {
	$peer_delete = mysqli_query(
		$connection,
		'DELETE FROM `'.$settings['db_prefix'].'peers` '.
		// that matches the given info_hash and peer_id
		'WHERE info_hash=\''.$peer['info_hash'].'\' AND peer_id=\''.$peer['peer_id'].'\';'
	);
	if ( $peer_delete ) {
		return true;
	} else {
		tracker_error('Failed to remove peer.');
	}
}
