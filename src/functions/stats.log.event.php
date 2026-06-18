<?php

declare(strict_types=1);

////	stats_log_event
// Appends one row to the opt-in stat-tracking events ledger for an announce
// lifecycle event ('started', 'stopped', or 'completed'). This is the shared
// body of the phoenix.peer.new / phoenix.peer.stopped / phoenix.download.complete
// hooks — they each just call it with their event name. No-ops unless stats are
// enabled and $event is opted into stats_events.
//
// Privacy contract: the peer_id is used transiently to derive a coarse client
// label and the IP for a minified geo lookup — NEITHER is ever stored. Only the
// torrent owner, client label, ISO geo codes, time, info_hash, and event name
// are persisted.
/**
 * @param PhoenixSettings $settings
 * @param array<string, mixed> $peer
 */
function stats_log_event(mysqli $connection, array $settings, int $time, array $peer, string $event): void
{
    // Gate 1: stats off entirely.
    if (empty($settings['stats_enabled'])) {
        return;
    }
    // Gate 2: this event class is not being logged.
    if (! in_array($event, $settings['stats_events'], true)) {
        return;
    }

    require_once __DIR__.'/stats.client.detect.php';
    require_once __DIR__.'/stats.geo.lookup.php';
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
        'event' => $event,
        'client' => stats_client_detect((string) $peer['peer_id']),
        'user' => torrent_user($connection, $settings, $stats_info_hash),
        'country' => $stats_geo['country'],
        'continent' => $stats_geo['continent'],
    ]);
}
