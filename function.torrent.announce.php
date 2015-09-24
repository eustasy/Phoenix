<?php

function torrent_announce($connection, $settings) {

	require_once __DIR__.'/function.mysqli.fetch.once.php';

	// begin response
	$response = 'd8:intervali'.$settings['announce_interval'].
		'e12:min intervali'.$settings['min_interval'].
		'e5:peers';

	$sql = 'SELECT COUNT(*) AS `count` FROM `'.$settings['db_prefix'].'peers` '.
		'WHERE `info_hash`=\''.$_GET['info_hash'].'\';';
	$peer_count = mysqli_fetch_once($connection, $sql);
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
				if ( $peer['compactv4'] != null ) {
					$peers .= hex2bin($peer['compactv4']);
				}
				if ( $peer['compactv6'] != null ) {
					$peersv6 .= hex2bin($peer['compactv6']);
				}
			// END IF Compact

			} else {
				// IF IPv4
				if ( $peer['ipv4'] != null ) {
					$response .= 'd2:ip'.strlen($peer['ipv4']).':'.$peer['ipv4'].
						'4:porti'.$peer['portv4'];
				// IF IPv6
				} else if ( $peer['ipv6'] != null ) {
					$response .= 'd2:ip'.strlen($peer['ipv6']).':'.$peer['ipv6'].
						'4:porti'.$peer['portv6'];
				}

				// IF Peer ID
				if ( !$_GET['no_peer_id'] ) {
					$response .= '7:peer id20:'.hex2bin($peer['peer_id']);
				} // END IF Peer ID

				$response .= 'ee';

			}
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
