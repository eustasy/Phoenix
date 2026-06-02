<?php

declare(strict_types=1);

////	announce_check_rate_limit
// Check if the same IP announced for the same torrent with a different peer_id recently.
// This prevents rapid fake-peer injection by using a tighter time window (min_interval/5).
// Calls tracker_error() and exits if rate limit is exceeded.

/**
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $peer
 */
function announce_check_rate_limit(mysqli $connection, array $settings, array $peer, int $time): void
{
    require_once __DIR__.'/../model/peers.count.rate.php';

    $ip_threshold = $time - intval($settings['min_interval'] / 5);
    $count = peers_count_rate($connection, $settings, $peer, $ip_threshold);

    if ($count > 0) {
        tracker_error('Announce rate limit exceeded.');
    }
}
