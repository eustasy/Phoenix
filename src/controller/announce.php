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
 * @param PhoenixSettings $settings
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
    if ($peer['info_hash'] === false || strlen($peer['info_hash']) !== 40) {
        tracker_error('Info Hash is invalid.', 'never');
    }

    // Torrent allowed? Closed-tracker check (BEP 27 private torrents): reject
    // announces for any info_hash not registered on this tracker.
    if (
        ! $settings['open_tracker'] &&
        ! in_array($peer['info_hash'], $allowed_torrents)
    ) {
        tracker_error('Torrent is not allowed.', 'never');
    }

    // peer_id: required, 40 hex chars (see info_hash note above).
    if ($peer['peer_id'] === false || strlen($peer['peer_id']) !== 40) {
        tracker_error('Peer ID is invalid.', 'never');
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
        tracker_error('Unable to obtain client IP.');
    }

    $resolved = peer_resolve_addresses($candidates, (bool)$settings['reject_private_ips']);
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

    // Validate each family's port independently against the smallint(5) unsigned
    // listening-port range (1-65535). A value outside it — from ?port= or a
    // client-supplied ip:port, which parse_ipv4()/parse_ipv6() do not bound —
    // can't be stored or connected to, so DROP that family (address + port)
    // rather than failing the whole announce: the two families are independent,
    // so a valid pair in the other still stands. Ports diverge per family only
    // via a client-supplied ip:port (allow_client_ip); ?port= applies to both
    // equally. $bad_port marks a range rejection so a client whose only problem
    // is the port still gets a "port" error below, not a generic "no IP" one.
    $bad_port = false;
    if (is_int($peer['portv4']) && ($peer['portv4'] < 1 || $peer['portv4'] > 65535)) {
        $peer['ipv4'] = false;
        $peer['portv4'] = false;
        $bad_port = true;
    }
    if (is_int($peer['portv6']) && ($peer['portv6'] < 1 || $peer['portv6'] > 65535)) {
        $peer['ipv6'] = false;
        $peer['portv6'] = false;
        $bad_port = true;
    }

    // A family counts only when BOTH its address and its port are present.
    $has_v4 = $peer['ipv4'] && $peer['portv4'];
    $has_v6 = $peer['ipv6'] && $peer['portv6'];

    // Every out-of-range port was dropped and nothing usable is left → the port
    // was the fault, so report it as such rather than falling through to the
    // address / missing-port guards below (which would misattribute it).
    if ($bad_port && ! $has_v4 && ! $has_v6) {
        tracker_error('Missing or invalid port.', 'never');
    }

    // Require a usable client address. With neither family resolved — e.g. a
    // private REMOTE_ADDR dropped by reject_private_ips and no public fallback —
    // there is nothing to register.
    if (! $peer['ipv4'] && ! $peer['ipv6']) {
        tracker_error('Missing or invalid IP address.');
    }

    // Require a listening port. BitTorrent's `port` is mandatory, and a peer
    // with an address but no port (port omitted, ?port=0, or a bare address
    // carrying no ip:port) can't be connected to. Reject it here as a client
    // fault rather than binding a false/0 port into the NOT NULL peers column,
    // which would otherwise store an unreachable peer or fail the insert with a
    // server-fault message (and, with report_errors on, spurious monitor noise).
    if (! $has_v4 && ! $has_v6) {
        tracker_error('Missing port.', 'never');
    }

    ////	Parse Optional Parameters
    require_once __DIR__.'/../functions/peer.parse.announce.optional.php';
    $peer = array_merge($peer, peer_parse_announce_optional($_GET, $settings));

    ////	Rate Limiting
    require_once __DIR__.'/../functions/announce.check.rate.limit.php';
    announce_check_rate_limit($connection, $settings, $peer, $time);

    ////	Handle Peer Event
    // Apply the announce event (select old row, dispatch stopped/completed/
    // new/changed/access, fire lifecycle hooks). Returns false for 'stopped',
    // where the client expects an empty body.
    require_once __DIR__.'/../functions/peer.handle.event.php';
    $event = $_GET['event'] ?? null;
    if (! peer_handle_event($connection, $settings, $time, $peer, $event)) {
        return '';
    }

    ////	Cleanup (probabilistic)
    if (
        ! $settings['clean_with_cron'] &&
        mt_rand(1, 100) <= $settings['clean_request_percent']
    ) {
        require_once __DIR__.'/../functions/task.clean.php';
        task_clean($connection, $settings, $time, 'auto');
    }

    ////	Build Peer List Response
    require_once __DIR__.'/../model/peers.count.swarm.php';
    require_once __DIR__.'/../functions/peer.select.strategy.php';
    require_once __DIR__.'/../model/peers.select.active.php';

    $stale_threshold = $time - ($settings['announce_rec_interval'] + $settings['announce_min_interval']);
    $counts = peers_count_swarm($connection, $settings, $peer['info_hash'], $stale_threshold);
    $strategy = peer_select_strategy($peer, $counts['complete'], $counts['incomplete'], $settings);
    $rows = peers_select_active($connection, $settings, $peer, $stale_threshold, $strategy);

    // BEP 24: echo the client's own public address back to it, unless disabled.
    require_once __DIR__.'/../functions/peer.external.ip.php';
    $external_ip = $settings['announce_external_ip']
        ? peer_external_ip($peer, $_SERVER)
        : false;

    if (isset($_GET['xml'])) {
        require_once __DIR__.'/../views/xml.announce.php';
        header('Content-Type: application/xml; charset=UTF-8');

        return view_announce_xml($counts, $settings, $rows, $external_ip);
    }
    if (isset($_GET['json'])) {
        require_once __DIR__.'/../views/json.announce.php';
        header('Content-Type: application/json; charset=UTF-8');

        return view_announce_json($counts, $settings, $rows, $external_ip);
    }
    require_once __DIR__.'/../views/bencode.announce.php';
    // text/plain matches the de-facto convention for tracker bencode and stops
    // PHP from defaulting to text/html. ISO-8859-1 lines up with the global
    // default_charset set in phoenix.php so the raw bytes pass through.
    header('Content-Type: text/plain; charset=ISO-8859-1');

    return view_announce_bencode($counts, $settings, $rows, (bool)$peer['compact'], (bool)$peer['no_peer_id'], $external_ip);
}
