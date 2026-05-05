<?php

declare(strict_types=1);

////	peer_insert
// REPLACE INTO peer (insert or update all fields when peer state has changed).
// Uses REPLACE to handle both new peers and state changes (IP change, seeding transition, etc.).
// Returns true on success, calls tracker_error() on failure.

function peer_insert(mysqli $connection, array $settings, int $time, array $peer): true {

	$compactv4 = '';
	$compactv6 = '';
	if ( !empty($peer['ipv4'])) {
		// BEP 23: compact IPv4 peer = 4-byte big-endian IP + 2-byte big-endian port (6 bytes).
		// Stored as hex so it survives the latin1 DB column without corruption.
		$compactv4 = bin2hex(pack('Nn', ip2long($peer['ipv4']), $peer['portv4']));
	}
	if ( !empty($peer['ipv6'])) {
		// BEP 7: compact IPv6 peer = 16-byte address (inet_pton) + 2-byte big-endian port (18 bytes).
		$compactv6 = bin2hex(inet_pton($peer['ipv6']).pack('n', $peer['portv6']));
	}

	$peer_new = mysqli_query(
		$connection,
		'REPLACE INTO `'.$settings['db_prefix'].'peers` '.
		'(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `ipv4`, `ipv6`, `portv4`,`portv6`, `uploaded`, `downloaded`, `left`, `state`, `updated`) '.
		'VALUES ('.
			// 40-byte info_hash in HEX
			'\''.$peer['info_hash'].'\', '.
			// 40-byte peer_id in HEX
			'\''.$peer['peer_id'].'\', '.
			// 12-byte compacted peer info
			'\''.$compactv4.'\', '.
			'\''.$compactv6.'\', '.
			// dotted decimal string ip
			'\''.$peer['ipv4'].'\', '.
			'\''.$peer['ipv6'].'\', '.
			// integer port
			'\''.$peer['portv4'].'\', '.
			'\''.$peer['portv6'].'\', '.
			// transfer counters
			'\''.$peer['uploaded'].'\', '.
			'\''.$peer['downloaded'].'\', '.
			// integer left
			'\''.$peer['left'].'\', '.
			// integer state
			'\''.$peer['state'].'\', '.
			// unix timestamp
			'\''.$time.'\''.
		');'
	);

	if ( !$peer_new ) {
		tracker_error('Failed to add new peer.');
	}
	return true;
}
