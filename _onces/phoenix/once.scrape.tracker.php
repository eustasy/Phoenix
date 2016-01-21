<?php

$peers = 'SELECT
		`p`.`info_hash` AS `info_hash`,
		SUM(`p`.`state`=\'1\') AS `seeders`,
		SUM(`p`.`state`=\'0\') AS `leechers`
	FROM `'.$settings['db_prefix'].'peers` AS `p`
	GROUP BY `info_hash`;';
$torrents = 'SELECT
		`p`.`info_hash` AS `info_hash`,
		`p`.`downloads` AS `downloads`
	FROM `'.$settings['db_prefix'].'torrents` AS `p`
	GROUP BY `info_hash`;';
$peers = mysqli_query($connection, $peers);
$torrents = mysqli_query($connection, $torrents);

if (
	!$peers ||
	!$torrents
) {
	tracker_error('Unable to scrape for that torrent.');
} else {
	require_once $settings['onces'].'once.scrape.output.php';
}
