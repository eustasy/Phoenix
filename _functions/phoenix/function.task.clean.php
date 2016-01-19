<?php

function task_clean($connection, $settings, $time) {
	require_once $settings['functions'].'function.task.log.php';

	$clean = mysqli_query(
		$connection,
		// Delete Peers that have been idle twice the announce interval.
		'DELETE FROM `'.$settings['db_prefix'].'peers` WHERE `updated` < '.
		'\''. ( $time - ( $settings['announce_interval'] * 3 ) ) .'\';'
	);
	// TODO Also clean out possible test items.

	if ( $clean ) {
		$task = task_log($connection, $settings, 'clean', $time);
	}

	return $clean;

}
