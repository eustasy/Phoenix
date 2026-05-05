<?php

declare(strict_types=1);

////	admin_setup_action
//  Handles database setup/reset action.
//  Returns message string on completion.

function admin_setup_action($connection, $settings, $time, $tables_installed) {
	require_once $settings['model'].'db.drop.php';
	require_once $settings['model'].'db.create.php';

	if (!$settings['db_reset'] && $tables_installed) {
		// Reset not allowed and tables already exist
		return false;
	}

	$success = true;

	////	Drop existing tables if needed

	if ($tables_installed) {
		if (
			!db_drop_table($connection, $settings, 'peers') ||
			!db_drop_table($connection, $settings, 'tasks') ||
			!db_drop_table($connection, $settings, 'torrents')
		) {
			$success = false;
		}
	}

	////	Create database tables

	if (!db_create($connection, $settings)) {
		$success = false;
	}

	////	Return result message

	if ($success) {
		require_once $settings['model'].'task.log.php';
		task_log($connection, $settings, 'install', $time);
		return 'Your MySQL Tracker Database has been setup.';
	} else {
		return 'Could not setup the MySQL Database.';
	}
}
