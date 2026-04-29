<?php

function task_clean($connection, $settings, $time) {
	require_once $settings['functions'].'function.task.log.php';
	$cleaned = true;

	// Remove peers that have not announced within 3x the announce interval.
	// 1x = the normal re-announce window; 2x = one missed announce (grace); 3x = clearly gone.
	// Also purges rows with test-reserved prefixes/values left by the test suite.
	$sql[] = 'DELETE FROM `'.$settings['db_prefix'].'peers`'.
		' WHERE `updated` < \''. ( $time - ( $settings['announce_interval'] * 3 ) ) .'\''.
		' OR `info_hash` LIKE \'__TEST_%\''.
		' OR `info_hash` = \'DELETEME\''.
		' OR `peer_id` LIKE \'__TEST_%\''.
		' OR `peer_id` = \'DELETEME\';';
	$sql[] = 'DELETE FROM `'.$settings['db_prefix'].'tasks`'.
		' WHERE `name` LIKE \'__TEST_%\''.
		' OR `name` = \'DELETEME\';';
	$sql[] = 'DELETE FROM `'.$settings['db_prefix'].'torrents`'.
		' WHERE `info_hash` LIKE \'__TEST_%\''.
		' OR `info_hash` = \'DELETEME\''.
		' OR `name` LIKE \'__TEST_%\''.
		' OR `name` = \'DELETEME\';';
	foreach ( $sql as $query ) {
		$result = mysqli_query($connection, $query);
		if ( !$result ) {
			$cleaned = false;
		}
	}

	if ( $cleaned ) {
		task_log($connection, $settings, 'clean', $time);
	}

	return $cleaned;

}
