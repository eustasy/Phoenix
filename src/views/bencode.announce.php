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
	// Begin response — keys in lexicographic order per bencode spec.
	$response = 'd8:completei'.$counts['complete'].
		'e10:incompletei'.$counts['incomplete'].
		'e8:intervali'.$settings['announce_interval'].
		'e12:min intervali'.$settings['min_interval'].
		'e5:peers';

	if ( $compact ) {
		// BEP 23 (IPv4) and BEP 7 (IPv6) compact peer format.
		// Build compact binary strings (6 bytes per IPv4 peer, 18 bytes per IPv6).
		$v4 = '';
		$v6 = '';
		foreach ( $rows as $row ) {
			if ( $row['compactv4'] != null ) {
				$v4 .= hex2bin($row['compactv4']);
			}
			if ( $row['compactv6'] != null ) {
				$v6 .= hex2bin($row['compactv6']);
			}
		}
		// Bencode the binary strings with length prefix.
		$response .= strlen($v4).':'.$v4;
		$response .= '6:peers6'.strlen($v6).':'.$v6;
	} else {
		// Non-compact mode: list of peer dictionaries.
		$response .= 'l';
		foreach ( $rows as $row ) {
			// Each peer is a bencode dict with 'ip', 'port', and optionally 'peer id'.
			if ( $row['ipv4'] != null ) {
				$response .= 'd2:ip'.strlen($row['ipv4']).':'.$row['ipv4'].
					'4:porti'.$row['portv4'].'e';
			} else if ( $row['ipv6'] != null ) {
				$response .= 'd2:ip'.strlen($row['ipv6']).':'.$row['ipv6'].
					'4:porti'.$row['portv6'].'e';
			}
			if ( !$no_peer_id ) {
				$response .= '7:peer id20:'.hex2bin($row['peer_id']);
			}
			$response .= 'e';
		}
		$response .= 'e';
	}

	return $response.'e';
}
