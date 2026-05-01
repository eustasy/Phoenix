<?php

////	scrape_query_peers
// Query peer statistics for the given info_hashes.
// Returns mysqli_result or false on failure.

function scrape_query_peers($connection, $settings, $where_clause) {
	$sql = 'SELECT
		`p`.`info_hash` AS `info_hash`,
		SUM(`p`.`state`=\'1\') AS `seeders`,
		SUM(`p`.`state`=\'0\') AS `leechers`
	FROM `'.$settings['db_prefix'].'peers` AS `p`';
	return mysqli_query($connection, $sql.$where_clause.' GROUP BY `info_hash`;');
}
