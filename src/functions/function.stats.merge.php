<?php

////	stats_merge
// Merge peer counts and download totals into a single stats array.
// Returns array with all stats as integers, or false if either input is false.

function stats_merge($peer_counts, $download_totals) {
	if (!$peer_counts || !$download_totals) {
		return false;
	}

	$stats = array();
	$stats['seeders']   = intval($peer_counts['seeders']);
	$stats['leechers']  = intval($peer_counts['leechers']);
	$stats['torrents']  = intval($peer_counts['torrents']);
	$stats['downloads'] = intval($download_totals['downloads']);
	$stats['traffic']   = intval($download_totals['traffic']);
	$stats['peers']     = $stats['seeders'] + $stats['leechers'];

	return $stats;
}
