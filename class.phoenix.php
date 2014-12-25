<?php

// TODO Honour private setting.

// Phoenix Core
class phoenix {

	// Database API
	public static $api;

	// Open a Database Connection
	public static function open() {
		self::$api = (
			new phoenix_mysqli()
		);
	}

	// close database connection
	public static function close() {
		// trigger __destruct()
		self::$api = null;
	}

	// database cleanup
	public static function clean() {
		// run cleanup once per announce interval
		// check 'clean_idle_peers'% of the time to avoid excess queries
		if (mt_rand(1, $_SERVER['tracker']['clean_idle_peers']) == 1) {
			// unix timestamp
			$time = time();

			// fetch last cleanup time
			$last = self::$api->fetch_once(
				// select last cleanup from tasks
				"SELECT value FROM `{$_SERVER['tracker']['db_prefix']}tasks` WHERE name='prune'"
			);

			// first clean cycle?
			if (($last[0] + 0) == 0) {
				self::$api->query(
					// set tasks value prune to current unix timestamp
					"REPLACE INTO `{$_SERVER['tracker']['db_prefix']}tasks` VALUES('prune', {$time})"
				) OR tracker_error('could not perform maintenance');

				self::$api->query(
					// delete peers that have been idle too long
					"DELETE FROM `{$_SERVER['tracker']['db_prefix']}peers` WHERE updated < " .
					// idle length is announce interval x 2
					($time - ($_SERVER['tracker']['announce_interval'] * 2))
				) OR tracker_error('could not perform maintenance');
			}
			// prune idle peers
			else if (($last[0] + $_SERVER['tracker']['announce_interval']) < $time) {
				self::$api->query(
					// set tasks value prune to current unix timestamp
					"UPDATE `{$_SERVER['tracker']['db_prefix']}tasks` SET value={$time} WHERE name='prune'"
				) OR tracker_error('could not perform maintenance');

				self::$api->query(
					// delete peers that have been idle too long
					"DELETE FROM `{$_SERVER['tracker']['db_prefix']}peers` WHERE updated < " .
					// idle length is announce interval x 2
					($time - ($_SERVER['tracker']['announce_interval'] * 2))
				) OR tracker_error('could not perform maintenance');
			}
		}
	}

	// insert new peer
	public static function new_peer() {
		self::$api->query(
			// insert into the peers table
			"INSERT IGNORE INTO `{$_SERVER['tracker']['db_prefix']}peers` " .
			// table columns
			'(info_hash, peer_id, compact, ip, port, state, updated) ' .
			// 20-byte info_hash, 20-byte peer_id
			"VALUES ('{$_GET['info_hash']}', '{$_GET['peer_id']}', '" .
			// 6-byte compacted peer info
			self::$api->escape_sql(pack('Nn', ip2long($_GET['ip']), $_GET['port'])) . "', " .
			// dotted decimal string ip, integer port, integer state and unix timestamp updated
			"'{$_GET['ip']}', {$_GET['port']}, {$_SERVER['tracker']['seeding']}, " . time() . '); '
		) OR tracker_error('Failed to add new peer.');
	}

	// full peer update
	public static function update_peer() {
		// update peer
		self::$api->query(
			// update the peers table
			"UPDATE `{$_SERVER['tracker']['db_prefix']}peers` " .
			// set the 6-byte compacted peer info
			"SET compact='" . self::$api->escape_sql(pack('Nn', ip2long($_GET['ip']), $_GET['port'])) .
			// dotted decimal string ip, integer port
			"', ip='{$_GET['ip']}', port={$_GET['port']}, " .
			// integer state and unix timestamp updated
			"state={$_SERVER['tracker']['seeding']}, updated=" . time() .
			// that matches the given info_hash and peer_id
			" WHERE info_hash='{$_GET['info_hash']}' AND peer_id='{$_GET['peer_id']}'"
		) OR tracker_error('failed to update peer data');
	}

	// update peers last access time
	public static function update_last_access() {
		// update peer
		self::$api->query(
			// set updated to the current unix timestamp
			"UPDATE `{$_SERVER['tracker']['db_prefix']}peers` SET updated=" . time() .
			// that matches the given info_hash and peer_id
			" WHERE info_hash='{$_GET['info_hash']}' AND peer_id='{$_GET['peer_id']}'"
		) OR tracker_error('failed to update peers last access');
	}

	// remove existing peer
	public static function delete_peer() {
		// delete peer
		self::$api->query(
			// delete a peer from the peers table
			"DELETE FROM `{$_SERVER['tracker']['db_prefix']}peers` " .
			// that matches the given info_hash and peer_id
			"WHERE info_hash='{$_GET['info_hash']}' AND peer_id='{$_GET['peer_id']}'"
		) OR tracker_error('failed to remove peer data');
	}

	// tracker event handling
	public static function event() {
		// execute peer select
		$pState = self::$api->fetch_once(
			// select a peer from the peers table
			"SELECT ip, port, state FROM `{$_SERVER['tracker']['db_prefix']}peers` " .
			// that matches the given info_hash and peer_id
			"WHERE info_hash='{$_GET['info_hash']}' AND peer_id='{$_GET['peer_id']}'"
		);

		// process tracker event
		switch ((isset($_GET['event']) ? $_GET['event'] : false)) {
			// client gracefully exited
			case 'stopped':
				// remove peer
				if (isset($pState[2])) self::delete_peer();
				break;
			// client completed download
			case 'completed':
				// force seeding status
				$_SERVER['tracker']['seeding'] = 1;
			// client started download
			case 'started':
			// client continuing download
			default:
				// new peer
				if (!isset($pState[2])) self::new_peer();
				// peer status
				else if (
					// check that ip addresses match
					$pState[0] != $_GET['ip'] ||
					// check that listening ports match
					($pState[1]+0) != $_GET['port'] ||
					// check whether seeding status match
					($pState[2]+0) != $_SERVER['tracker']['seeding']
				) self::update_peer();
				// update time
				else self::update_last_access();
		}
	}

	// tracker peer list
	public static function peers() {
		// fetch peer total
		$total = self::$api->fetch_once(
			// select a count of the number of peers that match the given info_hash
			"SELECT COUNT(*) FROM `{$_SERVER['tracker']['db_prefix']}peers` WHERE info_hash='{$_GET['info_hash']}'"
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
			"FROM `{$_SERVER['tracker']['db_prefix']}peers` WHERE info_hash='{$_GET['info_hash']}'" .
			// less peers than requested, so return them all
			($total[0] <= $_GET['numwant'] ? ';' :
				// if the total peers count is low, use SQL RAND
				($total[0] <= $_SERVER['tracker']['random_limit'] ?
					" ORDER BY RAND() LIMIT {$_GET['numwant']};" :
					// use a more efficient but less accurate RAND
					" LIMIT {$_GET['numwant']} OFFSET " .
					mt_rand(0, ($total[0]-$_GET['numwant']))
				)
			);

		// begin response
		$response = 'd8:intervali' . $_SERVER['tracker']['announce_interval'] .
		            'e12:min intervali' . $_SERVER['tracker']['min_interval'] .
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

	// tracker scrape
	public static function scrape() {
		// scrape response
		$response = 'd5:filesd';

		// scrape info_hash
		if (isset($_GET['info_hash'])) {
			// scrape
			$scrape = self::$api->fetch_once(
				// select total seeders and leechers
				'SELECT SUM(state=1), SUM(state=0) ' .
				// from peers
				"FROM `{$_SERVER['tracker']['db_prefix']}peers` " .
				// that match info_hash
				"WHERE info_hash='" . self::$api->escape_sql($_GET['info_hash']) . "'"
			) OR tracker_error('unable to scrape the requested torrent');

			// 20-byte info_hash, integer complete, integer downloaded, integer incomplete
			$response .= "20:{$_GET['info_hash']}d8:completei" . ($scrape[0]+0) .
			             'e10:downloadedi0e10:incompletei' . ($scrape[1]+0) . 'ee';
		}
		// full scrape
		else {
			// scrape
			$sql = 'SELECT ' .
				// info_hash, total seeders and leechers
				'info_hash, SUM(state=1), SUM(state=0) ' .
				// from peers
				"FROM `{$_SERVER['tracker']['db_prefix']}peers` " .
				// grouped by info_hash
				'GROUP BY info_hash';

			// build response
			self::$api->full_scrape($sql, $response);
		}

		// send response
		echo $response . 'ee';
	}

	// tracker statistics
	public static function stats() {
		// statistics
		$stats = self::$api->fetch_once(
			// select seeders and leechers
			'SELECT SUM(state=1), SUM(state=0), ' .
			// unique torrents
			'COUNT(DISTINCT info_hash) ' .
			// from peers
			"FROM `{$_SERVER['tracker']['db_prefix']}peers` "
		) OR tracker_error('failed to retrieve tracker statistics');

		// output format
		switch ($_GET['stats']) {
			// xml
			case 'xml':
				header('Content-Type: text/xml');
				echo '<?xml version="1.0" encoding="ISO-8859-1"?>' .
				     '<tracker version="$Id: phoenix.php 164 2010-01-23 22:08:58Z trigunflame $">' .
				     '<peers>' . number_format($stats[0] + $stats[1]) . '</peers>' .
				     '<seeders>' . number_format($stats[0]) . '</seeders>' .
				     '<leechers>' . number_format($stats[1]) . '</leechers>' .
				     '<torrents>' . number_format($stats[2]) . '</torrents></tracker>';
				break;

			// json
			case 'json':
				header('Content-Type: text/javascript');
				echo '{"tracker":{"version":"$Id: phoenix.php 164 2010-01-23 22:08:58Z trigunflame $",' .
				     '"peers": "' . number_format($stats[0] + $stats[1]) . '",' .
					 '"seeders":"' . number_format($stats[0]) . '",' .
					 '"leechers":"' . number_format($stats[1]) . '",' .
				     '"torrents":"' . number_format($stats[2]) . '"}}';
				break;

			// standard
			default:
				echo '<!doctype html><html><head><meta http-equiv="content-type" content="text/html; charset=UTF-8">' .
				     '<title>Phoenix: $Id: phoenix.php 164 2010-01-23 22:08:58Z trigunflame $</title>' .
					 '<body><pre>' . number_format($stats[0] + $stats[1]) .
				     ' peers (' . number_format($stats[0]) . ' seeders + ' . number_format($stats[1]) .
				     ' leechers) in ' . number_format($stats[2]) . ' torrents</pre></body></html>';
		}
	}
}