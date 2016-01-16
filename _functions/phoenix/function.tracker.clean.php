<?php

function tracker_clean($connection, $settings, $time) {
	require_once $settings['functions'].'function.task.php';

	$clean = mysqli_query(
		$connection,
		// Delete Peers that have been idle twice the announce interval.
		'DELETE FROM `'.$settings['db_prefix'].'peers` WHERE `updated` < '.
		'\''. ( $time - ( $settings['announce_interval'] * 2 ) ) .'\';'
	);
	if ( !$clean ) {
		tracker_error('Could not perform maintenance.');
	}

	$task = task($connection, $settings, 'prune', $time);
	if ( !$task ) {
		tracker_error('Could not set last maintenance time.');
	}

	return true;

}
