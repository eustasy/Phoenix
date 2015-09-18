<?php

function torrent_announce() {

	global $connection, $settings;

	require_once __DIR__.'/once.db.connect.php';

	// begin response
	$response = 'd8:intervali' . $settings['announce_interval'].'e12:min intervali' . $settings['min_interval'].'e5:peers';

	require_once __DIR__.'/function.mysqli.fetch.once.php';
	$peer_count = mysqli_fetch_once('SELECT COUNT(*) AS `count` FROM `'.$settings['db_prefix'].'peers` WHERE `info_hash`=\''.$_GET['info_hash'].'\';');
	if ( !$peer_count ) {
		$peer_count = 0;
	} else {
		$peer_count = $peer_count['count'];
	}

	$sql = 'SELECT * FROM `'.$settings['db_prefix'].'peers` WHERE `info_hash`=\''.$_GET['info_hash'].'\'';

	// IF there are more peers than requested,
	// only return the ones we need.
	if ( $peer_count > $_GET['numwant'] ) {
		$sql .= ' LIMIT '.$_GET['numwant'].' OFFSET '.mt_rand(0, ($peer_count-$_GET['numwant'])).';';

	// IF there are more peers than the random limit.
	} else if ( $peer_count > $settings['random_limit'] ) {
		$sql .= ' ORDER BY RAND();';
	}

	// IF Compact
	if ( $_GET['compact'] ) {
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
		while ( $peer = mysqli_fetch_assoc($query) ) {

			// IF Compact
			if ( $_GET['compact'] ) {
				if ( $peer['compact'] != null ) {
					$peers .= hex2bin($peer['compact']);
				}
				if ( $peer['compactv6'] != null ) {
					$peersv6 .= hex2bin($peer['compactv6']);
				}
			// END IF Compact

			// IF No Peer ID
			} else if ( $_GET['no_peer_id'] ) {
				if ( $peer['ipv4'] != null ) {
					$response .= 'd2:ip'.strlen($peer['ip']).':'.$peer['ip'].'4:porti'.$peer['portv4'].'ee';
				} elseif ( $peer['ipv6'] != null ) {
					$response .= 'd2:ip'.strlen($peer['ipv6']).':'.$peer['ipv6'].'4:porti'.$peer['portv6'].'ee';
				}
			// END IF No Peer ID

			// IF Normal
			} else {
				if ( $peer['ip'] != null ) {
					$response .= 'd2:ip'.strlen($peer['ipv4']).':'.$peer['ipv4'].'7:peer id20:'.hex2bin($peer['peer_id']).'4:porti'.$peer['portv4'].'ee';
				} elseif ( $peer['ipv6'] != null ) {
					$response .= 'd2:ip'.strlen($peer['ipv6']).':'.$peer['ipv6'].'7:peer id20:'.hex2bin($peer['peer_id']).'4:porti'.$peer['portv6'].'ee';
				}
			} // END IF Normal

		}
	}

	// IF Compact
	if ( $_GET['compact'] ) {
		// 6-byte compacted peer info
		$response .= strlen($peers).':'.$peers;
		$response .= '6:peers6'.strlen($peersv6).':'.$peersv6;
	// END IF Compact

	// IF Not Compact
	} else {
		$response .= 'e';
	} // END IF Not Compact

	echo $response.'e';

}
