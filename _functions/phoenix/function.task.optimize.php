<?php

function task_optimize($connection, $settings, $time, $table = false, $and_default = true) {
	require_once $settings['functions'].'function.task.log.php';

	$tables = array();
	if ( $table ) {
		$tables[] = $table;
	}
	if ( $and_default ) {
		$tables[] = 'peers';
		$tables[] = 'tasks';
		$tables[] = 'torrents';
	}

	if ( empty($tables) ) {
		return true;
	}

	$sql = '';
	foreach ( $tables as $table ) {
		$sql .= 'CHECK TABLE `'.$settings['db_prefix'].$table.'`;'.
			'ANALYZE TABLE `'.$settings['db_prefix'].$table.'`;'.
			'REPAIR TABLE `'.$settings['db_prefix'].$table.'`;'.
			'OPTIMIZE TABLE `'.$settings['db_prefix'].$table.'`;';
	}
	$result = mysqli_multi_query($connection, $sql);
	// mysqli_multi_query buffers all result sets internally; each must be consumed
	// before the connection can be used again, even if we don't inspect the results.
	if ( $result ) {
		while ( mysqli_more_results($connection) ) {
			mysqli_next_result($connection);
			$res = mysqli_store_result($connection);
			if ( $res ) {
				mysqli_free_result($res);
			}
		}
	}

	if ( $result ) {
		task_log($connection, $settings, 'optimize', $time);
	}

	return $result;

}
