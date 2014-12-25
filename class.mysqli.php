<?php

// MySQLi Database API
class phoenix_mysqli {

	// database connection
	public $db;

	// connect to database
	public function __construct() {

		// IF persistent connection
		if ( $_SERVER['tracker']['db_persist']) {
			$_SERVER['tracker']['db_host'] = 'p:'.$_SERVER['tracker']['db_host'];
		}

		// attempt to connect
		$this->db = new mysqli(
			$_SERVER['tracker']['db_host'],
			$_SERVER['tracker']['db_user'],
			$_SERVER['tracker']['db_pass'],
			$_SERVER['tracker']['db_name']
		);

		// error out if something happened
		if ($this->db->connect_errno) tracker_error(
			$this->db->connect_errno . ' - ' .
			$this->db->connect_error
		);
	}

	// close database connection
	public function __destruct() {
		$this->db->close();
	}

	// make sql safe
	public function escape_sql($sql) {
		return $this->db->real_escape_string($sql);
	}

	// query database
	public function query($sql) {
		return $this->db->query($sql);
	}

	// return one row
	public function fetch_once($sql) {
		// execute query
		$query = $this->db->query($sql) OR tracker_error($this->db->error);
		$result = $query->fetch_row();

		// cleanup
		$query->close();

		// return
		return $result;
	}

	// return compact peers
	public function peers_compact($sql, &$peers) {
		// fetch peers
		$query = $this->db->query($sql) OR tracker_error('failed to select compact peers');

		// build response
		while($peer = $query->fetch_row()) $peers .= $peer[0];

		// cleanup
		$query->close();
	}

	// return dictionary peers
	public function peers_dictionary($sql, &$response) {
		// fetch peers
		$query = $this->db->query($sql) OR tracker_error('failed to select peers');

		// dotted decimal string ip, 20-byte peer_id, integer port
		while($peer = $query->fetch_row()) $response .= 'd2:ip' . strlen($peer[1]) . ":{$peer[1]}" . "7:peer id20:{$peer[0]}4:porti{$peer[2]}ee";

		// cleanup
		$query->close();
	}

	// return dictionary peers without peer_id
	public function peers_dictionary_no_peer_id($sql, &$response) {
		// fetch peers
		$query = $this->db->query($sql) OR tracker_error('failed to select peers');

		// dotted decimal string ip, integer port
		while($peer = $query->fetch_row()) $response .= 'd2:ip' . strlen($peer[0]) . ":{$peer[0]}4:porti{$peer[1]}ee";

		// cleanup
		$query->close();
	}

	// full scrape of all torrents
	public function full_scrape($sql, &$response) {
		// fetch scrape
		$query = $this->db->query($sql) OR tracker_error('unable to perform a full scrape');

		// 20-byte info_hash, integer complete, integer downloaded, integer incomplete
		while ($scrape = $query->fetch_row()) $response .= "20:{$scrape[0]}d8:completei{$scrape[1]}e10:downloadedi0e10:incompletei{$scrape[2]}ee";

		// cleanup
		$query->close();
	}
}