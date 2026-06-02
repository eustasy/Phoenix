<?php

declare(strict_types=1);

////	announce_controller
// Drives the BEP 3 announce flow: sanitize → validate → resolve IPs →
// rate-limit → fetch old peer state → dispatch on $_GET['event'] → run
// probabilistic cleanup → build and render the peer-list response.
// Returns the rendered body string. Returns '' for ?event=stopped (the
// client expects no body). Calls tracker_error() on validation failure
// (which exits, same contract as before).

/**
 * @param array<string, mixed> $settings
 * @param array<int, string> $allowed_torrents
 */
function announce_controller(mysqli $connection, array $settings, int $time, array $allowed_torrents = []): string
{

    ////	Sanitize & Validate Input
    require_once __DIR__.'/../functions/sanitize.tracker.php';
    $peer = sanitize_tracker_params();

    // info_hash: required, 40 hex chars. sanitize_tracker_params returns false
    // when the value is missing or fails validation — under strict_types the
    // strlen() call below would raise a TypeError on false, so check first.
    if ($peer['info_hash'] === false || strlen((string) $peer['info_hash']) !== 40) {
        tracker_error('Info Hash is invalid.');
    }

    // Torrent allowed? (closed tracker check)
    if (
        ! $settings['open_tracker'] &&
        ! in_array($peer['info_hash'], $allowed_torrents)
    ) {
        tracker_error('Torrent is not allowed.');
    }

    // peer_id: required, 40 hex chars (see info_hash note above).
    if ($peer['peer_id'] === false || strlen((string) $peer['peer_id']) !== 40) {
        tracker_error('Peer ID is invalid.');
    }

    ////	Resolve IP Addresses & Ports
    require_once __DIR__.'/../functions/parse.ipv4.php';
    require_once __DIR__.'/../functions/parse.ipv6.php';
    require_once __DIR__.'/../functions/peer.address.candidates.php';
    require_once __DIR__.'/../functions/peer.resolve.addresses.php';

    $peer['ipv4'] = false;
    $peer['ipv6'] = false;
    $peer['port'] = false;
    $peer['portv4'] = false;
    $peer['portv6'] = false;

    if (isset($_GET['port'])) {
        $peer['port'] = intval($_GET['port']);
    }

    $candidates = peer_address_candidates($settings, $_GET, $_SERVER);
    if (! count($candidates)) {
        tracker_error('Unable to obtain client IP');
    }

    $resolved = peer_resolve_addresses($candidates);
    $peer['ipv4'] = $resolved['ipv4'];
    $peer['ipv6'] = $resolved['ipv6'];
    $peer['portv4'] = $resolved['portv4'];
    $peer['portv6'] = $resolved['portv6'];

    // Fall back to ?port= when the resolved address didn't supply its own port
    if ($peer['port'] && ! $peer['portv4']) {
        $peer['portv4'] = $peer['port'];
    }
    if ($peer['port'] && ! $peer['portv6']) {
        $peer['portv6'] = $peer['port'];
    }

    // Validate we got at least one valid IP+port pair
    if (
        (
            ! $peer['ipv4'] &&
            ! $peer['portv4']
        ) &&
        (
            ! $peer['ipv6'] &&
            ! $peer['portv6']
        )
    ) {
        tracker_error('Unable to get IP and Port');
    }

    ////	Parse Optional Parameters
    require_once __DIR__.'/../functions/peer.parse.announce.optional.php';
    $peer = array_merge($peer, peer_parse_announce_optional($_GET, $settings));

    ////	Rate Limiting
    require_once __DIR__.'/../functions/announce.check.rate.limit.php';
    announce_check_rate_limit($connection, $settings, $peer, $time);

    ////	Handle Peer Event
    require_once __DIR__.'/../model/peer.select.php';
    require_once __DIR__.'/../functions/peer.changed.php';
    require_once __DIR__.'/../functions/phoenix.hook.php';

    $peer['old'] = peer_select($connection, $settings, $peer);

    $event = $_GET['event'] ?? null;

    // EVENT: stopped — remove the peer and return empty; the client expects no body
    if ($event === 'stopped') {
        require_once __DIR__.'/../model/peer.delete.php';
        peer_delete($connection, $settings, $peer);
        phoenix_hook('peer.stopped', $connection, $settings, $time, $peer);

        return '';
    }

    // EVENT: completed — increment downloads and force seeding state
    if ($event === 'completed') {
        $peer['state'] = 1;
        require_once __DIR__.'/../model/torrent.increment.downloads.php';
        torrent_increment_downloads($connection, $settings, (string) $peer['info_hash']);
        phoenix_hook('download.complete', $connection, $settings, $time, $peer);
    }

    // CHANGED or NEW peer — REPLACE the row, then run new/change hook
    if (peer_changed($peer, $peer['old'])) {
        require_once __DIR__.'/../model/peer.insert.php';
        peer_insert($connection, $settings, $time, $peer);
        phoenix_hook($peer['old'] ? 'peer.change' : 'peer.new', $connection, $settings, $time, $peer);

        // UNCHANGED peer — bump the access timestamp only
    } else {
        require_once __DIR__.'/../model/peer.update.php';
        peer_update($connection, $settings, $time, $peer);
        phoenix_hook('peer.access', $connection, $settings, $time, $peer);
    }

    ////	Cleanup (probabilistic)
    if (
        ! $settings['clean_with_cron'] &&
        mt_rand(1, 100) <= $settings['clean_with_requests']
    ) {
        require_once __DIR__.'/../functions/task.clean.php';
        task_clean($connection, $settings, $time);
    }

    ////	Build Peer List Response
    require_once __DIR__.'/../model/peers.count.swarm.php';
    require_once __DIR__.'/../functions/peer.select.strategy.php';
    require_once __DIR__.'/../model/peers.select.active.php';

    $stale_threshold = $time - ($settings['announce_interval'] + $settings['min_interval']);
    $counts = peers_count_swarm($connection, $settings, $peer['info_hash'], $stale_threshold);
    $strategy = peer_select_strategy($peer, $counts['complete'], $counts['incomplete'], $settings);
    $rows = peers_select_active($connection, $settings, $peer, $stale_threshold, $strategy);

    if (isset($_GET['xml'])) {
        require_once __DIR__.'/../views/xml.announce.php';
        header('Content-Type: text/xml');

        return view_announce_xml($counts, $settings, $rows);
    }
    if (isset($_GET['json'])) {
        require_once __DIR__.'/../views/json.announce.php';
        header('Content-Type: application/json');

        return view_announce_json($counts, $settings, $rows);
    }
    require_once __DIR__.'/../views/bencode.announce.php';
    // text/plain matches the de-facto convention for tracker bencode and stops
    // PHP from defaulting to text/html. ISO-8859-1 lines up with the global
    // default_charset set in phoenix.php so the raw bytes pass through.
    header('Content-Type: text/plain; charset=ISO-8859-1');

    return view_announce_bencode($counts, $settings, $rows, (bool)$peer['compact'], (bool)$peer['no_peer_id']);
}
