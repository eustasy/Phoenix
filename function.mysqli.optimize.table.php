<?php

function optimize_table($connection, $settings, $table) {
	$sql = 'CHECK TABLE `'.$settings['db_prefix'].$table.'`;'.
		'ANALYZE TABLE `'.$settings['db_prefix'].$table.'`;'.
		'REPAIR TABLE `'.$settings['db_prefix'].$table.'`;'.
		'OPTIMIZE TABLE `'.$settings['db_prefix'].$table.'`;';
	mysqli_multi_query($connection, $sql, MYSQLI_STORE_RESULT);
	return true;
}
