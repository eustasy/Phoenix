<?php

function peer_access($connection, $settings, $time, $peer) {
	$peer_access = mysqli_query(
		$connection,
		'UPDATE `'.$settings['db_prefix'].'peers` '.
		'SET `updated`=\''.$time.'\', `left`=\''.$peer['left'].'\' '.
		'WHERE `info_hash`=\''.$peer['info_hash'].'\' AND `peer_id`=\''.$peer['peer_id'].'\';'
	);
	if ( $peer_access ) {
		return true;
	} else {
		tracker_error('Failed to update peers last access.');
	}
}
