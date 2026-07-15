<?php

function task_clean(mysqli $connection, array $settings, int $time): bool {
	require_once $settings['functions'].'function.task.log.php';
	$cleaned = true;

	// Remove peers that have not announced within 3x the announce interval.
	// 1x = the normal re-announce window; 2x = one missed announce (grace); 3x = clearly gone.
	$sql = array();
	$sql[] = 'DELETE FROM `'.$settings['db_prefix'].'peers`'.
		' WHERE `updated` < \''. ( $time - ( $settings['announce_interval'] * 3 ) ) .'\';';

	// Purge test-suite residue, but only against a TESTING_-prefixed table set
	// (_tests/phoenix.php and once.test.initialize.php append TESTING_ to
	// db_prefix); production tables cannot contain these rows. The underscores
	// must stay backslash-escaped: in LIKE, a bare `_` matches any single
	// character, and latin1_swedish_ci compares case-insensitively, so an
	// unescaped pattern also matches real torrent names such as "untested".
	if ( strpos($settings['db_prefix'], 'TESTING_') !== false ) {
		$sql[] = 'DELETE FROM `'.$settings['db_prefix'].'peers`'.
			' WHERE `info_hash` LIKE \'\\_\\_TEST\\_%\''.
			' OR `info_hash` = \'DELETEME\''.
			' OR `peer_id` LIKE \'\\_\\_TEST\\_%\''.
			' OR `peer_id` = \'DELETEME\';';
		$sql[] = 'DELETE FROM `'.$settings['db_prefix'].'tasks`'.
			' WHERE `name` LIKE \'\\_\\_TEST\\_%\''.
			' OR `name` = \'DELETEME\';';
		$sql[] = 'DELETE FROM `'.$settings['db_prefix'].'torrents`'.
			' WHERE `info_hash` LIKE \'\\_\\_TEST\\_%\''.
			' OR `info_hash` = \'DELETEME\''.
			' OR `name` LIKE \'\\_\\_TEST\\_%\''.
			' OR `name` = \'DELETEME\';';
	}
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
