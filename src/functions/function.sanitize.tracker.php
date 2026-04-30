<?php
////    sanitize_tracker_params
// Parse and sanitize info_hash and peer_id from a query string for tracker endpoints.
// Returns an associative array: info_hash, info_hashes, peer_id.

require_once $settings['functions'].'function.sanitize.maybe_binary_to_hex.php';

function sanitize_tracker_params($query_string = null) {
	if ($query_string === null) {
		$query_string = $_SERVER['QUERY_STRING'] ?? '';
	}
	$peer = [
		'info_hash' => false,
		'info_hashes' => [],
		'peer_id' => false,
	];
	$params = explode('&', $query_string);
	foreach ($params as $param) {
		$param = explode('=', $param, 2);
		if ($param[0] === 'info_hash') {
			$peer['info_hashes'][] = maybe_binary_to_hex($param[1]);
		} elseif ($param[0] === 'peer_id') {
			$peer['peer_id'] = maybe_binary_to_hex($param[1]);
		}
	}
	if (!empty($peer['info_hashes'][0])) {
		$peer['info_hash'] = $peer['info_hashes'][0];
	}
	return $peer;
}
