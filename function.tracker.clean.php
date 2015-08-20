<?php

function tracker_clean() {

	global $connection, $settings, $time;

	require_once __DIR__.'/once.db.connect.php';

	if ( mt_rand(1, 100) <= $settings['clean_idle_peers'] ) {

		$clean = mysqli_query(
			$connection,
			// Delete Peers that have been idle twice the announce interval.
			'DELETE FROM `'.$settings['db_prefix'].'peers` WHERE `updated` < '.
			'\''. ( $time - ( $settings['announce_interval'] * 2 ) ) .'\''
		);
		if ( !$clean ) {
			tracker_error('Could not perform maintenance.');
		}

		$task = mysqli_query($connection, 'REPLACE INTO `'.$settings['db_prefix'].'tasks` (`name`, `value`) VALUES (\'prune\', \''.$time.'\');');
		if ( !$task ) {
			tracker_error('Could not set last maintenance time.');
		}

	}

	return true;

}
