<?php

declare(strict_types=1);

////	view_announce_bencode
// Renders a BitTorrent announce response as bencode (BEP 3).
// Keys must be in lexicographic order per the bencode spec.
//
// Arguments:
//   $counts: array{complete: int, incomplete: int} — swarm counts
//   $settings: config array (needs announce_interval, min_interval)
//   $rows: array of peer rows from peers_select_active()
//   $compact: bool — whether client requested compact mode (BEP 23)
//   $no_peer_id: bool — whether client requested peer_id omission
//
// In compact mode, $rows must have 'compactv4' and 'compactv6' hex columns.
// In non-compact mode, $rows must have 'ipv4', 'portv4', 'ipv6', 'portv6',
// and 'peer_id' columns.
function view_announce_bencode(
	array $counts,
	array $settings,
	array $rows,
	bool $compact,
	bool $no_peer_id
): string {
	// Helpers loaded inside the function so coverage tracks them per-call
	// (top-of-file require_once executes once per process and may show as
	// uncovered if another test loaded the file first).
	require_once __DIR__.'/../functions/peer.format.bencode.php';
	require_once __DIR__.'/../functions/peers.format.compact.php';
	// Begin response — keys in lexicographic order per bencode spec.
	$response = 'd8:completei'.$counts['complete'].
		'e10:incompletei'.$counts['incomplete'].
		'e8:intervali'.$settings['announce_interval'].
		'e12:min intervali'.$settings['min_interval'].
		'e5:peers';

	if ( $compact ) {
		// BEP 23 (IPv4, 6 bytes per peer) and BEP 7 (IPv6, 18 bytes per peer).
		// peers_format_compact does the hex2bin assembly; we just bencode the
		// length-prefixed binary strings here.
		$compact_peers = peers_format_compact($rows);
		$response .= strlen($compact_peers['v4']).':'.$compact_peers['v4'];
		$response .= '6:peers6'.strlen($compact_peers['v6']).':'.$compact_peers['v6'];
	} else {
		// Non-compact mode: list of peer dictionaries.
		// Each peer dict has 'ip', optionally 'peer id', then 'port' — the only
		// lexicographic order BEP 3 permits.
		$response .= 'l';
		foreach ( $rows as $row ) {
			$response .= peer_format_bencode($row, !$no_peer_id);
		}
		$response .= 'e';
	}

	return $response.'e';
}
