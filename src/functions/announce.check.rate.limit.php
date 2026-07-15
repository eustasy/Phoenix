<?php

declare(strict_types=1);

////	announce_check_rate_limit
// Throttle rapid fake-peer injection: reject when the same IP already has
// announce_rate_limit OTHER peer_ids for this torrent, updated within
// announce_rate_window seconds. Calls tracker_error() and exits when exceeded.
//
// The check is keyed on IP alone, so co-located clients (a shared home NAT, or
// unrelated subscribers behind one CGNAT IPv4) count together — announce_rate_limit
// is how many other active peer_ids one IP may carry on a torrent before the next
// is throttled, and 0 disables the check entirely. See the setting comments in
// phoenix.default.php.

/**
 * @param PhoenixSettings $settings
 * @param array<string, mixed> $peer
 */
function announce_check_rate_limit(mysqli $connection, array $settings, array $peer, int $time): void
{
    require_once __DIR__.'/../model/peers.count.rate.php';

    // 0 (or negative) disables the check.
    $limit = intval($settings['announce_rate_limit']);
    if ($limit <= 0) {
        return;
    }

    $window = intval($settings['announce_rate_window']);
    $ip_threshold = $time - $window;
    $count = peers_count_rate($connection, $settings, $peer, $ip_threshold);

    if ($count >= $limit) {
        // BEP 31: the limit clears once the rate window passes, so tell the
        // client to retry after it.
        tracker_error('Announce rate limit exceeded.', $window);
    }
}
