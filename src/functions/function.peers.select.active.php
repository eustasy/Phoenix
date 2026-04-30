<?php

declare(strict_types=1);

////	peers_select_active
// Selects up to $peer['numwant'] fresh peer rows for a torrent's info_hash,
// excluding the announcer (matched by peer_id) and stale rows (older than
// $stale_threshold). The WHERE/ORDER clauses returned by peer_select_strategy
// are appended verbatim. Calls tracker_error and exits when the query fails.
function peers_select_active(mysqli $connection, array $settings, array $peer, int $stale_threshold, array $strategy): array {
	$where = '`info_hash`=\''.$peer['info_hash'].'\' '.
		'AND `peer_id`!=\''.$peer['peer_id'].'\' '.
		'AND `updated`>'.$stale_threshold.
		$strategy['where'];
	$sql = 'SELECT * FROM `'.$settings['db_prefix'].'peers` '.
		'WHERE '.$where.$strategy['order'].' '.
		'LIMIT '.$peer['numwant'].';';

	$query = mysqli_query($connection, $sql);
	if ( !$query ) {
		tracker_error('Failed to select peers.');
	}

	$rows = array();
	while ( $row = mysqli_fetch_assoc($query) ) {
		$rows[] = $row;
	}
	return $rows;
}
