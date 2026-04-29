<?php

require_once $settings['functions'].'function.mysqli.fetch.once.php';

$stale_threshold = $time - ($settings['announce_interval'] + $settings['min_interval']);

// Count active seeders and leechers for swarm stats
$counts = mysqli_fetch_once($connection,
	'SELECT '.
		'IFNULL(SUM(`state`=\'1\'), 0) AS `complete`, '.
		'IFNULL(SUM(`state`=\'0\'), 0) AS `incomplete` '.
	'FROM `'.$settings['db_prefix'].'peers` '.
	'WHERE `info_hash`=\''.$peer['info_hash'].'\' '.
	'AND `updated`>'.$stale_threshold.';'
);
$complete   = $counts ? intval($counts['complete'])   : 0;
$incomplete = $counts ? intval($counts['incomplete']) : 0;

// begin response — keys in lexicographic order per bencode spec
$response = 'd8:completei'.$complete.
	'e10:incompletei'.$incomplete.
	'e8:intervali'.$settings['announce_interval'].
	'e12:min intervali'.$settings['min_interval'].
	'e5:peers';

$where = '`info_hash`=\''.$peer['info_hash'].'\' AND `peer_id`!=\''.$peer['peer_id'].'\' AND `updated`>'.$stale_threshold;
$order = '';

if ( $peer['left'] == 0 ) {
	// Completed: only show leechers (exclude fellow seeders); nearest-to-done first
	// converts near-complete leechers to seeders fastest, growing available bandwidth
	$where .= ' AND `state`=\'0\'';
	$order  = ' ORDER BY `left` ASC, `updated` DESC';
} else if ( $peer['left'] > 0 && $peer['left'] > $peer['downloaded'] ) {
	// Just started (session downloaded < remaining, so likely <50% done):
	// filter to peers likely >50% complete or seeders, spread across most recent
	// to avoid concentrating connections on the same top peers
	// note: downloaded is session-only so this is an approximation
	$where .= ' AND (`state`=\'1\' OR `downloaded` > `left`)';
	$order  = ' ORDER BY `updated` DESC';
} else if ( $peer['left'] > 0 ) {
	// In progress (session downloaded >= remaining, so likely >=50% done):
	// most complete first but randomised within quality tiers to spread load
	// note: downloaded is session-only so the >=50% threshold is an approximation
	$order = ' ORDER BY `left` ASC, RAND()';
} else {
	// State unknown (left not reported): return most recently active peers
	$order = ' ORDER BY `updated` DESC';
}

$sql = 'SELECT * FROM `'.$settings['db_prefix'].'peers` WHERE '.$where.$order.' LIMIT '.$peer['numwant'].';';

// IF Compact
if ( $peer['compact'] ) {
	$peers = '';
	$peersv6 = '';
// END IF Compact

// IF Not Compact
} else {
	$response .= 'l';
} // END IF Not Compact

$query = mysqli_query($connection, $sql);
if ( !$query ) {
	tracker_error('Failed to select peers.');
} else {
	while ( $return = mysqli_fetch_assoc($query) ) {
		// IF Compact
		if ( $peer['compact'] ) {
			if ( $return['compactv4'] != null ) {
				$peers .= hex2bin($return['compactv4']);
			}
			if ( $return['compactv6'] != null ) {
				$peersv6 .= hex2bin($return['compactv6']);
			}
		// END IF Compact

		} else {
			// IF IPv4
			if ( $return['ipv4'] != null ) {
				$response .= 'd2:ip'.strlen($return['ipv4']).':'.$return['ipv4'].
					'4:porti'.$return['portv4'].'e';
			// IF IPv6
			} else if ( $return['ipv6'] != null ) {
				$response .= 'd2:ip'.strlen($return['ipv6']).':'.$return['ipv6'].
					'4:porti'.$return['portv6'].'e';
			}

			// IF Peer ID
			if ( !$peer['no_peer_id'] ) {
				$response .= '7:peer id20:'.hex2bin($return['peer_id']);
			} // END IF Peer ID

			$response .= 'e';

		}
	}
}

// IF Compact
if ( $peer['compact'] ) {
	// 6-byte compacted peer info
	$response .= strlen($peers).':'.$peers;
	$response .= '6:peers6'.strlen($peersv6).':'.$peersv6;
// END IF Compact

// IF Not Compact
} else {
	$response .= 'e';
} // END IF Not Compact

echo $response.'e';
