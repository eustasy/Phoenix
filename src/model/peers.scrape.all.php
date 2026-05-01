<?php

////	peers_scrape_all
// SELECT aggregated peer counts for ALL torrents (full scrape).
// Returns mysqli_result with columns: info_hash, seeders, leechers.
// GROUP BY info_hash, returns all torrents in the tracker.
// Used by: scrape.php (full scrape when enabled).

function peers_scrape_all($connection, $settings) {
	return mysqli_query($connection,
		'SELECT
			`p`.`info_hash` AS `info_hash`,
			SUM(`p`.`state`=\'1\') AS `seeders`,
			SUM(`p`.`state`=\'0\') AS `leechers`
		FROM `'.$settings['db_prefix'].'peers` AS `p`
		GROUP BY `p`.`info_hash`;'
	);
}
