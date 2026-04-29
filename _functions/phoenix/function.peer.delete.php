<?php

////	peer_delete
// Removes a single peer row identified by info_hash and peer_id; exits via tracker_error on failure.
function peer_delete(mysqli $connection, array $settings, array $peer): true {
	$peer_delete = mysqli_query(
		$connection,
		'DELETE FROM `'.$settings['db_prefix'].'peers` '.
		// that matches the given info_hash and peer_id
		'WHERE info_hash=\''.$peer['info_hash'].'\' AND peer_id=\''.$peer['peer_id'].'\';'
	);
	if ( !$peer_delete ) {
		tracker_error('Failed to remove peer.');
	}
	return true;
}
