<?php

////	torrents_scrape_all
// Query torrent statistics for all torrents (full scrape).
// Returns mysqli_result or false on failure.
function torrents_scrape_all(mysqli $connection, array $settings): mysqli_result|false {
	return mysqli_query($connection,
		'SELECT
			`t`.`info_hash` AS `info_hash`,
			`t`.`downloads` AS `downloads`
		FROM `'.$settings['db_prefix'].'torrents` AS `t`;'
	);
}
