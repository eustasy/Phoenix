<?php

function task($task, $value){
	$task = mysqli_query(
		$connection,
		'REPLACE INTO `'.$settings['db_prefix'].'tasks` (`name`, `value`) VALUES (\''.$task.\', \''.$value.'\');'
	);
	if ( $task ) {
		return true;
	} else {
		return false;
	}
}