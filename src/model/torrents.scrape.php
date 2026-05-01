<?php

////	torrents_scrape
// Query torrent statistics for the given info_hashes.
// Returns mysqli_result or false on failure.
function torrents_scrape(mysqli $connection, array $settings, string $where_clause): mysqli_result|false {
	$sql = 'SELECT
		`p`.`info_hash` AS `info_hash`,
		`p`.`size` AS `size`,
		`p`.`downloads` AS `downloads`
	FROM `'.$settings['db_prefix'].'torrents` AS `p`';
	return mysqli_query($connection, $sql.$where_clause.' GROUP BY `info_hash`;');
}
