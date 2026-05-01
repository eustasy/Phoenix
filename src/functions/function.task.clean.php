<?php

function task_clean(mysqli $connection, array $settings, int $time): bool {
	require_once $settings['functions'].'function.task.log.php';
	require_once $settings['model'].'peers.clean.php';
	require_once $settings['model'].'tasks.clean.php';
	require_once $settings['model'].'torrents.clean.php';
	$cleaned = true;

	// Remove peers that have not announced within 3x the announce interval.
	// 1x = the normal re-announce window; 2x = one missed announce (grace); 3x = clearly gone.
	// Also purges rows with test-reserved prefixes/values left by the test suite.
	$threshold = $time - ( $settings['announce_interval'] * 3 );
	$cleaned = peers_clean($connection, $settings, $threshold) && $cleaned;

	// Clean tasks and torrents tables (test/sentinel rows)
	$cleaned = tasks_clean($connection, $settings) && $cleaned;
	$cleaned = torrents_clean($connection, $settings) && $cleaned;

	if ( $cleaned ) {
		task_log($connection, $settings, 'clean', $time);
	}

	return $cleaned;

}
