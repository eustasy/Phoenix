<?php

function peer_completed($connection, $settings, $peer) {
	mysqli_query(
		$connection,
		'INSERT INTO `'.$settings['db_prefix'].'torrents` '.
		'(`info_hash`, `downloads`) '.
		'VALUES ('.
			// 40-byte info_hash in HEX
			'\''.$peer['info_hash'].'\', '.
			// initial value = 1
			'1'.
		') '.
		'ON DUPLICATE KEY UPDATE '.
			// if exists then increment
			'`downloads`=`downloads`+1;'
	);
	// Silently fail
	// tracker_error('Failed to update downloads count.');
	return true;
}
