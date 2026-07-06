<?php

declare(strict_types=1);

////	admin_torrent_peers_controller
// Renders the admin peer drill-down (page=peers): every peer in one torrent's
// swarm, each tagged with a detected client label. Reads the peers table
// directly, so it works for any info_hash — listed, unlisted, or entirely
// unregistered. The info_hash arrives from the query string, so it MUST pass
// maybe_binary_to_hex and be a 40-char hex string before any query; an invalid
// one bails via tracker_error. Read-only — no forms, so the only token built
// is for the layout's logout form. Dispatched by admin_panel_controller() for
// page=peers.

/** @param PhoenixSettings $settings */
function admin_torrent_peers_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../functions/sanitize.maybe_binary_to_hex.php';
    $raw = $_GET['info_hash'] ?? '';
    $info_hash = maybe_binary_to_hex(is_string($raw) ? $raw : '');
    if ($info_hash === false || strlen($info_hash) !== 40) {
        tracker_error('Info Hash is invalid.');
    }

    // Registry name (null when the swarm has no torrents row).
    require_once __DIR__.'/../model/torrent.select.one.php';
    $torrent = torrent_select_one($connection, $settings, $info_hash);
    $name = ($torrent !== false && is_string($torrent['name'])) ? $torrent['name'] : null;

    require_once __DIR__.'/../model/peers.select.by.torrent.php';
    $peers = peers_select_by_torrent($connection, $settings, $info_hash);

    // Tag each peer with a client label derived transiently from peer_id — it is
    // never stored, matching stats_client_detect's privacy contract.
    require_once __DIR__.'/../functions/stats.client.detect.php';
    $tagged = [];
    foreach ($peers as $peer) {
        $tagged[] = $peer + ['client' => stats_client_detect($peer['peer_id'])];
    }

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.torrent.peers.php';

    return view_admin_torrent_peers_html($settings, $info_hash, $name, $tagged, $csrf_token);
}
