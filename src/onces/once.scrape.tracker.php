<?php

// Full scrape: no WHERE clause, returns all tracked torrents.
// Only reached when $settings['full_scrape'] is true and no info_hash was given.
// $scrape is not pre-initialised here; once.scrape.output.php builds it from the results.
$peers    = mysqli_query($connection,
	'SELECT
		`p`.`info_hash` AS `info_hash`,
		SUM(`p`.`state`=\'1\') AS `seeders`,
		SUM(`p`.`state`=\'0\') AS `leechers`
	FROM `'.$settings['db_prefix'].'peers` AS `p`
	GROUP BY `p`.`info_hash`;'
);
$torrents = mysqli_query($connection,
	'SELECT
		`t`.`info_hash` AS `info_hash`,
		`t`.`downloads` AS `downloads`
	FROM `'.$settings['db_prefix'].'torrents` AS `t`;'
);

if (
	!$peers ||
	!$torrents
) {
	tracker_error('Unable to scrape for that torrent.');
} else {
	require_once $settings['onces'].'once.scrape.output.php';
}
