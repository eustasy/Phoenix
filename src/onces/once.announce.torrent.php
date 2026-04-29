<?php

require_once $settings['functions'].'function.mysqli.fetch.once.php';
require_once $settings['functions'].'function.peer.select.strategy.php';
require_once $settings['functions'].'function.peer.format.bencode.php';

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

$strategy = peer_select_strategy($peer, $complete, $incomplete, $settings);
$where    = '`info_hash`=\''.$peer['info_hash'].'\' AND `peer_id`!=\''.$peer['peer_id'].'\' AND `updated`>'.$stale_threshold.$strategy['where'];
$sql      = 'SELECT * FROM `'.$settings['db_prefix'].'peers` WHERE '.$where.$strategy['order'].' LIMIT '.$peer['numwant'].';';

if ( $peer['compact'] ) {
	$peers = '';
	$peersv6 = '';
} else {
	$response .= 'l';
}

$query = mysqli_query($connection, $sql);
if ( !$query ) {
	tracker_error('Failed to select peers.');
}

while ( $return = mysqli_fetch_assoc($query) ) {
	if ( $peer['compact'] ) {
		if ( $return['compactv4'] != null ) {
			$peers .= hex2bin($return['compactv4']);
		}
		if ( $return['compactv6'] != null ) {
			$peersv6 .= hex2bin($return['compactv6']);
		}
	} else {
		$response .= peer_format_bencode($return, !$peer['no_peer_id']);
	}
}

if ( $peer['compact'] ) {
	// 6-byte compacted peer info
	$response .= strlen($peers).':'.$peers;
	$response .= '6:peers6'.strlen($peersv6).':'.$peersv6;
} else {
	$response .= 'e';
}

echo $response.'e';
