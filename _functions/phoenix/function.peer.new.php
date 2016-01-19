<?php

function peer_new($connection, $settings, $time, $peer) {

	$compactv4 = '';
	$compactv6 = '';
	if ( !empty($peer['ipv4'])) {
		$compactv4 = bin2hex(pack('Nn', ip2long($peer['ipv4']), $peer['portv4']));
	}
	if ( !empty($peer['ipv6'])) {
		$compactv6 = bin2hex(inet_pton($peer['ipv6']).pack('n', $peer['portv6']));
	}

	$peer_new = mysqli_query(
		$connection,
		'REPLACE INTO `'.$settings['db_prefix'].'peers` '.
		'(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `ipv4`, `ipv6`, `portv4`,`portv6`, `left`, `state`, `updated`) '.
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
			// integer left
			'\''.$peer['left'].'\', '.
			// integer state
			'\''.$peer['state'].'\', '.
			// unix timestamp
			'\''.$time.'\''.
		');'
	);

	if ( $peer_new ) {
		return true;
	} else {
		tracker_error('Failed to add new peer.');
	}

}
