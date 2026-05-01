<?php

declare(strict_types=1);

////	announce_check_rate_limit
// Check if the same IP announced for the same torrent with a different peer_id recently.
// This prevents rapid fake-peer injection by using a tighter time window (min_interval/5).
// Calls tracker_error() and exits if rate limit is exceeded.

require_once $settings['functions'].'function.tracker.error.php';
require_once $settings['model'].'peers.count.rate.php';

function announce_check_rate_limit(mysqli $connection, array $settings, array $peer, int $time): void {
	$ip_threshold = $time - intval($settings['min_interval'] / 5);
	$count = peers_count_rate($connection, $settings, $peer, $ip_threshold);

	if ( $count > 0 ) {
		tracker_error('Announce rate limit exceeded.');
	}
}
