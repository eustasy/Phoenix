<?php

declare(strict_types=1);

require_once $settings['functions'].'function.peer.swarm.counts.php';
require_once $settings['functions'].'function.peer.select.strategy.php';
require_once $settings['functions'].'function.peer.format.bencode.php';
require_once $settings['functions'].'function.peers.select.active.php';
require_once $settings['functions'].'function.peers.format.compact.php';

$stale_threshold = $time - ($settings['announce_interval'] + $settings['min_interval']);

$counts = peer_swarm_counts($connection, $settings, $peer['info_hash'], $stale_threshold);

// Begin response — keys must be in lexicographic order per bencode spec.
$response = 'd8:completei'.$counts['complete'].
	'e10:incompletei'.$counts['incomplete'].
	'e8:intervali'.$settings['announce_interval'].
	'e12:min intervali'.$settings['min_interval'].
	'e5:peers';

$strategy = peer_select_strategy($peer, $counts['complete'], $counts['incomplete'], $settings);
$rows = peers_select_active($connection, $settings, $peer, $stale_threshold, $strategy);

if ( $peer['compact'] ) {
	$compact = peers_format_compact($rows);
	$response .= strlen($compact['v4']).':'.$compact['v4'];
	$response .= '6:peers6'.strlen($compact['v6']).':'.$compact['v6'];
} else {
	$response .= 'l';
	foreach ( $rows as $row ) {
		$response .= peer_format_bencode($row, !$peer['no_peer_id']);
	}
	$response .= 'e';
}

echo $response.'e';
