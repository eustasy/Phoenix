<?php

$sql = 'SELECT
		`p`.`info_hash` AS `info_hash`,
		SUM(`p`.`state`=\'1\') AS `seeders`,
		SUM(`p`.`state`=\'0\') AS `leechers`,
		`t`.`downloads` AS `downloads`
	FROM `'.$settings['db_prefix'].'peers` AS `p`
	LEFT JOIN `'.$settings['db_prefix'].'torrents` AS `t`
		ON `p`.`info_hash`=`t`.`info_hash`
	GROUP BY `info_hash`;';
$torrents = mysqli_query($connection, $sql);

if ( !$torrents ) {
	tracker_error('Unable to scrape the tracker.');
} else {
	require_once $settings['onces'].'once.scrape.output.php';
}
