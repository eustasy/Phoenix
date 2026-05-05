<?php

declare(strict_types=1);

////	task_log
// Records or replaces the last execution value for a named maintenance task.
// REPLACE INTO updates the existing row if the task name (primary key) already exists.
function task_log(mysqli $connection, array $settings, string $task, int $value): bool {
	$result = mysqli_query(
		$connection,
		'REPLACE INTO `'.$settings['db_prefix'].'tasks` (`name`, `value`) VALUES (\''.$task.'\', \''.$value.'\');'
	);
	return (bool) $result;
}
