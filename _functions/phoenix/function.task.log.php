<?php

////	task_log
// Records or replaces the last execution value for a named maintenance task.
// REPLACE INTO updates the existing row if the task name (primary key) already exists.
function task_log($connection, $settings, $task, $value) {
	$task = mysqli_query(
		$connection,
		'REPLACE INTO `'.$settings['db_prefix'].'tasks` (`name`, `value`) VALUES (\''.$task.'\', \''.$value.'\');'
	);
	if ( $task ) {
		return true;
	} else {
		return false;
	}
}
