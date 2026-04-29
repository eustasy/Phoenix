<?php

////	peer_access
// Updates the timestamp and transfer counters for a re-announcing peer whose address and state are unchanged.
function peer_access(mysqli $connection, array $settings, int $time, array $peer): true {
	$peer_access = mysqli_query(
		$connection,
		'UPDATE `'.$settings['db_prefix'].'peers` '.
		'SET `updated`=\''.$time.'\', `uploaded`=\''.$peer['uploaded'].'\', `downloaded`=\''.$peer['downloaded'].'\', `left`=\''.$peer['left'].'\' '.
		'WHERE `info_hash`=\''.$peer['info_hash'].'\' AND `peer_id`=\''.$peer['peer_id'].'\';'
	);
	if ( !$peer_access ) {
		tracker_error('Failed to update peers last access.');
	}
	return true;
}
