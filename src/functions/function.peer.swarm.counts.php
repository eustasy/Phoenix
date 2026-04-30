<?php

declare(strict_types=1);

////	peer_swarm_counts
// Returns active seeder/leecher counts for a torrent swarm by counting peers
// whose `updated` is newer than $stale_threshold. Returns
// array{complete: int, incomplete: int}; both keys are 0 when the SELECT
// returns no rows.
function peer_swarm_counts(mysqli $connection, array $settings, string $info_hash, int $stale_threshold): array {
	require_once $settings['functions'].'function.mysqli.fetch.once.php';
	$counts = mysqli_fetch_once($connection,
		'SELECT '.
			'IFNULL(SUM(`state`=\'1\'), 0) AS `complete`, '.
			'IFNULL(SUM(`state`=\'0\'), 0) AS `incomplete` '.
		'FROM `'.$settings['db_prefix'].'peers` '.
		'WHERE `info_hash`=\''.$info_hash.'\' '.
		'AND `updated`>'.$stale_threshold.';'
	);
	return array(
		'complete'   => $counts ? intval($counts['complete'])   : 0,
		'incomplete' => $counts ? intval($counts['incomplete']) : 0,
	);
}
