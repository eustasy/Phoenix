<?php

declare(strict_types=1);

////	peers_scrape
// SELECT aggregated peer counts per torrent (for scrape).
// Returns mysqli_result with columns: info_hash, seeders, leechers.
// GROUP BY info_hash, returns seeders/leechers per torrent.
// Used by: scrape.php (specific torrents).

/**
 * @param PhoenixSettings $settings
 */
function peers_scrape(mysqli $connection, array $settings, string $where_clause): mysqli_result|false
{
    $sql = 'SELECT
		`p`.`info_hash` AS `info_hash`,
		SUM(`p`.`state`=\'1\') AS `seeders`,
		SUM(`p`.`state`=\'0\') AS `leechers`
	FROM `'.$settings['db_prefix'].'peers` AS `p`';

    $result = mysqli_query($connection, $sql.$where_clause.' GROUP BY `info_hash`;');

    return $result instanceof mysqli_result ? $result : false;
}
