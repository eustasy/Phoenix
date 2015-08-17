<?php

function peer_new() {

	global $connection, $settings;

	require_once __DIR__.'/once.db.connect.php';

	$compactv4 = '';
	$compactv6 = '';
	if ( isset($_GET['ipv4'])) {
		$compactv4 = bin2hex(pack('Nn', ip2long($_GET['ipv4']), $_GET['portv4']));
	}
	if ( isset($_GET['ipv6'])) {
		$compactv6 = bin2hex( inet_pton($_GET['ipv6']) . pack('n', $_GET['portv6']) );
	}

	$peer_new = mysqli_query(
		$connection,
		'REPLACE INTO `'.$settings['db_prefix'].'peers` '.
		'(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `ipv4`, `ipv6`, `portv4`,`portv6`, `left`, `state`, `updated`) '.
		'VALUES ('.
			// 40-byte info_hash in HEX
			'\''.$_GET['info_hash'].'\', '.
			// 40-byte peer_id in HEX
			'\''.$_GET['peer_id'].'\', '.
			// 12-byte compacted peer info
			'\''.$compactv4.'\', '.
			'\''.$compactv6.'\', '.
			// dotted decimal string ip
			'\''.$_GET['ipv4'].'\', '.
			'\''.$_GET['ipv6'].'\', '.
			// integer port
			'\''.$_GET['portv4'].'\', '.
			'\''.$_GET['portv6'].'\', '.
			// integer left
			'\''.$_GET['left'].'\', '.
			// integer state
			'\''.$settings['seeding'].'\', '.
			// unix timestamp
			'\''.time().'\''.
		');'
	);

	if ( $peer_new ) {
		return true;
	} else {
		tracker_error('Failed to add new peer.');
	}

}
