<?php

function task_optimize($connection, $settings, $time, $table = false, $and_default = true) {
	require_once $settings['functions'].'function.task.log.php';

	if ( $table ) {
		$tables[] = $table;
	}
	if ( $and_default ) {
		$tables[] = 'peers';
		$tables[] = 'tasks';
		$tables[] = 'torrents';
	}

	$sql = '';
	foreach ( $tables as $table ) {
		$sql .= 'CHECK TABLE `'.$settings['db_prefix'].$table.'`;'.
			'ANALYZE TABLE `'.$settings['db_prefix'].$table.'`;'.
			'REPAIR TABLE `'.$settings['db_prefix'].$table.'`;'.
			'OPTIMIZE TABLE `'.$settings['db_prefix'].$table.'`;';
	}
	$result = mysqli_multi_query($connection, $sql, MYSQLI_STORE_RESULT);

	if ( $result ) {
		$task = task_log($connection, $settings, 'optimize', $time);
	}

	return $result;

}
