<?php

declare(strict_types=1);

////	peers_select_active
// SELECT active peers for a torrent (for announce response).
// Returns up to numwant rows excluding the announcer and stale peers.
// WHERE: info_hash, updated > stale_threshold, peer_id != announcer
// ORDER/LIMIT: per strategy (seeders-first, random, etc.)
// Returns array of peer rows, calls tracker_error() on failure.

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
