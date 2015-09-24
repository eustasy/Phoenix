<?php

function peer_access($connection, $settings, $time) {
	$peer_access = mysqli_query(
		$connection,
		'UPDATE `'.$settings['db_prefix'].'peers` '.
		'SET `updated`=\''.$time.'\', `left`=\''.$_GET['left'].'\' '.
		'WHERE `info_hash`=\''.$_GET['info_hash'].'\' AND `peer_id`=\''.$_GET['peer_id'].'\';'
	);
	if ( $peer_access ) {
		return true;
	} else {
		tracker_error('Failed to update peers last access.');
	}
}
