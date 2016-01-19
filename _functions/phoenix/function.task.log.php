<?php

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
