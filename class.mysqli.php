<?php

// MySQLi Database API
class phoenix_mysqli {

	// list allowed torrents
	public function array_build($sql, &$response) {
		$query = $this->db->query($sql) OR tracker_error('Unable to find allowed torrents.');
		// 20-byte info_hash, integer complete, integer downloaded, integer incomplete
		while ($torrent = $query->fetch_row()) {
			$response[] = $torrent[0];
		}
		$query->close();
		return $response;
	}

}