<?php

function task_optimize(mysqli $connection, array $settings, int $time, string|false $table = false, bool $and_default = true): bool {
	require_once $settings['model'].'task.log.php';

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
	foreach ( $tables as $t ) {
		$sql .= 'CHECK TABLE `'.$settings['db_prefix'].$t.'`;'.
			'ANALYZE TABLE `'.$settings['db_prefix'].$t.'`;'.
			'REPAIR TABLE `'.$settings['db_prefix'].$t.'`;'.
			'OPTIMIZE TABLE `'.$settings['db_prefix'].$t.'`;';
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
