<?php

// Scheduled maintenance entry point.
// Only runs when clean_with_cron is enabled; that setting disables the per-request fallback
// in public/announce.php so the announce path doesn't pay the clean/optimize overhead on any requests.
require_once __DIR__.'/../src/phoenix.php';

if ( $settings['clean_with_cron'] ) {
	require_once $settings['functions'].'function.task.clean.php';
	require_once $settings['functions'].'function.task.optimize.php';
	task_clean($connection, $settings, $time);
	task_optimize($connection, $settings, $time);
}
