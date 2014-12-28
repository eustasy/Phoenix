<?php

function peer_new() {

	global $connection, $settings;

	require_once __DIR__.'/once.db.connect.php';

	$peer_new = mysqli_query(
		$connection,
		'REPLACE INTO `'.$settings['db_prefix'].'peers` '.
		'(`info_hash`, `peer_id`, `compact`, `ip`, `port`, `left`, `state`, `updated`) '.
		'VALUES ('.
			// 20-byte info_hash, pre-escaped
			'\''.$_GET['info_hash'].'\', '.
			// 20-byte peer_id, pre-escaped
			'\''.$_GET['peer_id'].'\', '.
			// 6-byte compacted peer info
			'\''.mysqli_real_escape_string($connection, pack('Nn', ip2long($_GET['ip']), $_GET['port'])).'\', '.
			// dotted decimal string ip
			'\''.$_GET['ip'].'\', '.
			// integer port
			'\''.$_GET['port'].'\', '.
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