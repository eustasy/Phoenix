<?php

declare(strict_types=1);

////	torrents_scrape_all
// Query torrent statistics for all torrents (full scrape).
// Returns mysqli_result or false on failure.
function torrents_scrape_all(mysqli $connection, array $settings): mysqli_result|false
{
    $result = mysqli_query(
        $connection,
        'SELECT
			`t`.`info_hash` AS `info_hash`,
			`t`.`size`      AS `size`,
			`t`.`downloads` AS `downloads`
		FROM `'.$settings['db_prefix'].'torrents` AS `t`;',
    );

    return $result instanceof mysqli_result ? $result : false;
}
