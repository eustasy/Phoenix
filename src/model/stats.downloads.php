<?php

declare(strict_types=1);

////	stats_fetch_download_totals
// Fetch download and traffic totals from the database.
// Returns array with downloads and traffic, or false on failure.
function stats_fetch_download_totals(mysqli $connection, array $settings): array|false {
	require_once $settings['model'].'db.fetch.once.php';

	$sql = 'SELECT '.
		'SUM(`downloads`) AS `downloads`, '.
		'SUM(`downloads` * IFNULL(`size`, 0)) AS `traffic` '.
		'FROM `'.$settings['db_prefix'].'torrents`;';
	return db_fetch_once($connection, $sql);
}
