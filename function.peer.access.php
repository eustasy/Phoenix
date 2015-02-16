<?php

function peer_access() {

	global $connection, $settings;

	require_once __DIR__.'/once.db.connect.php';

	$peer_access = mysqli_query(
		$connection,
		'UPDATE `'.$settings['db_prefix'].'peers` '.
		'SET `updated`=\''.time().'\', `left`=\''.$_GET['left'].'\' '.
		'WHERE `info_hash`=\''.$_GET['info_hash'].'\' AND `peer_id`=\''.$_GET['peer_id'].'\''
	);

	if ( $peer_access ) {
		return true;
	} else {
		tracker_error('Failed to update peers last access.');
	}

}