<?php

////	scrape_query_all_peers
// Query peer statistics for all torrents (full scrape).
// Returns mysqli_result or false on failure.

function scrape_query_all_peers($connection, $settings) {
	return mysqli_query($connection,
		'SELECT
			`p`.`info_hash` AS `info_hash`,
			SUM(`p`.`state`=\'1\') AS `seeders`,
			SUM(`p`.`state`=\'0\') AS `leechers`
		FROM `'.$settings['db_prefix'].'peers` AS `p`
		GROUP BY `p`.`info_hash`;'
	);
}
