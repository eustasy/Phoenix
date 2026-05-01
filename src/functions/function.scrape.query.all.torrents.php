<?php

////	scrape_query_all_torrents
// Query torrent statistics for all torrents (full scrape).
// Returns mysqli_result or false on failure.

function scrape_query_all_torrents($connection, $settings) {
	return mysqli_query($connection,
		'SELECT
			`t`.`info_hash` AS `info_hash`,
			`t`.`downloads` AS `downloads`
		FROM `'.$settings['db_prefix'].'torrents` AS `t`;'
	);
}
