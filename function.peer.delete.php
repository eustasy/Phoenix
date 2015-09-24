<?php

function peer_delete($connection, $settings) {
	$peer_delete = mysqli_query(
		$connection,
		'DELETE FROM `'.$settings['db_prefix'].'peers` '.
		// that matches the given info_hash and peer_id
		'WHERE info_hash=\''.$_GET['info_hash'].'\' AND peer_id=\''.$_GET['peer_id'].'\';'
	);
	if ( $peer_delete ) {
		return true;
	} else {
		tracker_error('Failed to remove peer.');
	}
}
