<?php

////	peer_completed
// Inserts or increments the download counter when a peer sends event=completed.
function peer_completed(mysqli $connection, array $settings, array $peer): true {
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
