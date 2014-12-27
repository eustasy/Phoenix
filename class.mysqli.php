<?php

// MySQLi Database API
class phoenix_mysqli {

	// return compact peers
	public function peers_compact($sql, &$peers) {
		// fetch peers
		$query = $this->db->query($sql) OR tracker_error('Failed to select compact peers.');
		// build response
		while($peer = $query->fetch_row()) {
			$peers .= $peer[0];
		}
		// cleanup
		$query->close();
	}

	// return dictionary peers
	public function peers_dictionary($sql, &$response) {
		// fetch peers
		$query = $this->db->query($sql) OR tracker_error('Failed to select peers.');

		// dotted decimal string ip, 20-byte peer_id, integer port
		while($peer = $query->fetch_row()) {
			$response .= 'd2:ip' . strlen($peer[1]) . ":{$peer[1]}" . "7:peer id20:{$peer[0]}4:porti{$peer[2]}ee";
		}

		// cleanup
		$query->close();
	}

	// return dictionary peers without peer_id
	public function peers_dictionary_no_peer_id($sql, &$response) {
		$query = $this->db->query($sql) OR tracker_error('Failed to select peers.');
		// dotted decimal string ip, integer port
		while($peer = $query->fetch_row()) {
			$response .= 'd2:ip'.strlen($peer[0]).":{$peer[0]}4:porti{$peer[1]}ee";
		}
		$query->close();
	}

	// full scrape of all torrents
	public function full_scrape($sql, &$response) {
		
		$query->close();
	}

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