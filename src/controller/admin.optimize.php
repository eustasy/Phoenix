<?php

declare(strict_types=1);

////	admin_optimize_action
//  Handles database optimization action.
//  Returns message string on completion.

function admin_optimize_action($connection, $settings, $time) {
	require_once $settings['model'].'db.optimize.php';

	if (task_optimize($connection, $settings, $time)) {
		return 'Your MySQL Tracker Database has been optimized.';
	} else {
		return 'Could not optimize the MySQL Database.';
	}
}
