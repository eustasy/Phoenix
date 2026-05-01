<?php

////	scrape_query_torrents
// Query torrent statistics for the given info_hashes.
// Returns mysqli_result or false on failure.

function scrape_query_torrents($connection, $settings, $where_clause) {
	$sql = 'SELECT
		`p`.`info_hash` AS `info_hash`,
		`p`.`size` AS `size`,
		`p`.`downloads` AS `downloads`
	FROM `'.$settings['db_prefix'].'torrents` AS `p`';
	return mysqli_query($connection, $sql.$where_clause.' GROUP BY `info_hash`;');
}
