<?php

// Phoenix Core
class phoenix {


	// database cleanup
	public static function clean() {
		// run cleanup once per announce interval
		// check 'clean_idle_peers'% of the time to avoid excess queries
		if (mt_rand(1, $settings['clean_idle_peers']) == 1) {
			// unix timestamp
			$time = time();

			// fetch last cleanup time
			$last = self::$api->fetch_once(
				// select last cleanup from tasks
				"SELECT value FROM `{$settings['db_prefix']}tasks` WHERE name='prune'"
			);

			// first clean cycle?
			if ( ($last[0] + 0) == 0 ) {
				self::$api->query(
					// set tasks value prune to current unix timestamp
					"REPLACE INTO `{$settings['db_prefix']}tasks` VALUES ('prune', {$time})"
				) OR tracker_error('could not perform maintenance');

				self::$api->query(
					// delete peers that have been idle too long
					"DELETE FROM `{$settings['db_prefix']}peers` WHERE updated < " .
					// idle length is announce interval x 2
					($time - ($settings['announce_interval'] * 2))
				) OR tracker_error('could not perform maintenance');
			}
			// prune idle peers
			else if ( ($last[0] + $settings['announce_interval']) < $time) {
				self::$api->query(
					// set tasks value prune to current unix timestamp
					"UPDATE `{$settings['db_prefix']}tasks` SET value={$time} WHERE name='prune'"
				) OR tracker_error('could not perform maintenance');

				self::$api->query(
					// delete peers that have been idle too long
					"DELETE FROM `{$settings['db_prefix']}peers` WHERE updated < " .
					// idle length is announce interval x 2
					($time - ($settings['announce_interval'] * 2))
				) OR tracker_error('could not perform maintenance');
			}
		}
	}

	// full peer update
	public static function update_peer() {
		// update peer
		self::$api->query(
			// update the peers table
			"UPDATE `{$settings['db_prefix']}peers` " .
			// set the 6-byte compacted peer info
			"SET compact='" . mysqli_real_escape_string($connection, pack('Nn', ip2long($_GET['ip']), $_GET['port'])) .
			// dotted decimal string ip, integer port
			"', ip='{$_GET['ip']}', port={$_GET['port']}, " .
			// integer state and unix timestamp updated
			"state={$settings['seeding']}, updated=" . time() .
			// that matches the given info_hash and peer_id
			" WHERE info_hash='{$_GET['info_hash']}' AND peer_id='{$_GET['peer_id']}'"
		) OR tracker_error('failed to update peer data');
	}

	// tracker peer list
	public static function peers() {
		// fetch peer total
		$total = self::$api->fetch_once(
			// select a count of the number of peers that match the given info_hash
			"SELECT COUNT(*) FROM `{$settings['db_prefix']}peers` WHERE info_hash='{$_GET['info_hash']}'"
		) OR tracker_error('failed to select peer count');

		// select
		$sql = 'SELECT ' .
			// 6-byte compacted peer info
			($_GET['compact'] ? 'compact ' :
			// 20-byte peer_id
			(!$_GET['no_peer_id'] ? 'peer_id, ' : '') .
			// dotted decimal string ip, integer port
			'ip, port '
			) .
			// from peers table matching info_hash
			"FROM `{$settings['db_prefix']}peers` WHERE info_hash='{$_GET['info_hash']}'" .
			// less peers than requested, so return them all
			($total[0] <= $_GET['numwant'] ? ';' :
				// if the total peers count is low, use SQL RAND
				($total[0] <= $settings['random_limit'] ?
					" ORDER BY RAND() LIMIT {$_GET['numwant']};" :
					// use a more efficient but less accurate RAND
					" LIMIT {$_GET['numwant']} OFFSET " .
					mt_rand(0, ($total[0]-$_GET['numwant']))
				)
			);

		// begin response
		$response = 'd8:intervali' . $settings['announce_interval'] .
		            'e12:min intervali' . $settings['min_interval'] .
		            'e5:peers';

		// compact announce
		if ($_GET['compact']) {
			// peers list
			$peers = '';

			// build response
			self::$api->peers_compact($sql, $peers);

			// 6-byte compacted peer info
			$response .= strlen($peers) . ':' . $peers;
		}
		// dictionary announce
		else {
			// list start
			$response .= 'l';

			// include peer_id
			if (!$_GET['no_peer_id']) self::$api->peers_dictionary($sql, $response);
			// omit peer_id
			else self::$api->peers_dictionary_no_peer_id($sql, $response);

			// list end
			$response .= 'e';
		}

		// send response
		echo $response . 'e';

		// cleanup
		unset($peers);
	}

	// tracker statistics
	public static function allowed_torrents() {
		$response = array();
		$stats = self::$api->array_build(
			'SELECT `info_hash` FROM `'.$settings['db_prefix'].'torrents`',
			$response
		) OR tracker_error('Failed to retrieve allowed torrents.');
		return $response;
	}


}