<?php

// BEP 15 allows a single scrape request to carry multiple info_hashes.
// Build WHERE clauses and zero-initialise $scrape entries for all requested hashes
// so missing torrents still get a response rather than being silently omitted.
$peers_sql = 'SELECT
		`p`.`info_hash` AS `info_hash`,
		SUM(`p`.`state`=\'1\') AS `seeders`,
		SUM(`p`.`state`=\'0\') AS `leechers`
	FROM `'.$settings['db_prefix'].'peers` AS `p`';
$torrents_sql = 'SELECT
		`p`.`info_hash` AS `info_hash`,
		`p`.`size` AS `size`,
		`p`.`downloads` AS `downloads`
	FROM `'.$settings['db_prefix'].'torrents` AS `p`';
$where = 'WHERE ';
foreach ( $peer['info_hashes'] as $count => $info_hash ) {
	if ( $count > 0 ) {
		$where .= ' OR';
	}
	$where .= ' `p`.`info_hash`=\''.$info_hash.'\'';
	$scrape[$info_hash]['info_hash'] = $info_hash;
	$scrape[$info_hash]['seeders']   = 0;
	$scrape[$info_hash]['leechers']  = 0;
	$scrape[$info_hash]['downloads'] = 0;
	$scrape[$info_hash]['peers']     = 0;
	$scrape[$info_hash]['size']      = 0;
	$scrape[$info_hash]['traffic']   = 0;
}
$peers     = mysqli_query($connection, $peers_sql    .$where.' GROUP BY `info_hash`;');
$torrents  = mysqli_query($connection, $torrents_sql .$where.' GROUP BY `info_hash`;');

if (
	!$peers ||
	!$torrents
) {
	tracker_error('Unable to scrape for that torrent.');
} else {
	require_once $settings['onces'].'once.scrape.output.php';
}
