<?php

declare(strict_types=1);

////    sanitize_tracker_params
// Parse and sanitize info_hash and peer_id from a query string for tracker endpoints.
// Returns an associative array: info_hash, info_hashes, peer_id.

function sanitize_tracker_params(?string $query_string = null): array {
	require_once __DIR__.'/sanitize.maybe_binary_to_hex.php';

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
		// Skip bare keys (e.g. '&info_hash' with no '=value') so $param[1]
		// is never read undefined.
		if (!isset($param[1])) {
			continue;
		}
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
