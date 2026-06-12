<?php

declare(strict_types=1);

////	Peer Stopped
// The peer sent a "stopped" signal.
// The peer has been deleted.
//
// Stat-tracking logger (opt-in, off in the default stats_events). Records a
// 'stopped' event in the events ledger when stats are enabled and 'stopped' is
// a logged event. Privacy contract: the peer_id is used transiently to derive
// a coarse client label and the IP is used transiently for a minified geo
// lookup — NEITHER is ever stored.
//
// Runs inside phoenix_hook()'s scope, so $connection, $settings, $time, and
// $peer are already in scope; a bare `return;` exits the include.

/**
 * @var mysqli $connection
 * @var PhoenixSettings $settings
 * @var int $time
 * @var PhoenixPeer $peer
 */

// Gate 1: stats off entirely -> the include does almost nothing.
if (empty($settings['stats_enabled'])) {
    return;
}

// Gate 2: 'stopped' logging is opt-in (absent from the default stats_events).
if (! in_array('stopped', $settings['stats_events'], true)) {
    return;
}

require_once __DIR__.'/../functions/stats.client.detect.php';
require_once __DIR__.'/../functions/stats.geo.lookup.php';
require_once __DIR__.'/../model/torrent.user.php';
require_once __DIR__.'/../model/event.insert.php';

// $peer['ipv4']/['ipv6'] are string|false; pick the first usable one for the
// transient geo lookup (discarded immediately after).
$stats_ipv4 = $peer['ipv4'] ?? false;
$stats_ipv6 = $peer['ipv6'] ?? false;
$stats_ip = '';
if (is_string($stats_ipv4) && $stats_ipv4 !== '') {
    $stats_ip = $stats_ipv4;
} elseif (is_string($stats_ipv6) && $stats_ipv6 !== '') {
    $stats_ip = $stats_ipv6;
}

$stats_info_hash = (string) $peer['info_hash'];
$stats_geo = stats_geo_lookup($settings, $stats_ip);

event_insert($connection, $settings, [
    'time' => $time,
    'info_hash' => $stats_info_hash,
    'event' => 'stopped',
    'client' => stats_client_detect((string) $peer['peer_id']),
    'user' => torrent_user($connection, $settings, $stats_info_hash),
    'country' => $stats_geo['country'],
    'continent' => $stats_geo['continent'],
]);
