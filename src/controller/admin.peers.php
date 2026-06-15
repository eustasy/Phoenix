<?php

declare(strict_types=1);

////	admin_peers_controller
// Renders the admin global Peers page: a page of peers across every swarm, each
// tagged with a detected client label, newest-seen first. The swarm-wide totals
// (active peers, distinct swarms) come from the same aggregation the dashboard
// uses; the rows are paged via admin_peers_limit and an ?offset. Dispatched by
// admin_panel_controller() for page=peers when no info_hash is present (with
// one, the router routes to the live per-torrent drill-down instead).

/** @param PhoenixSettings $settings */
function admin_peers_controller(mysqli $connection, array $settings): string
{
    // Swarm-wide totals: active peers (= seeders + leechers) and the distinct
    // swarm count, reusing the dashboard/scrape aggregation.
    require_once __DIR__.'/../model/stats.peers.php';
    $counts = stats_fetch_peer_counts($connection, $settings);
    $total = $counts === false ? 0 : intval($counts['seeders']) + intval($counts['leechers']);
    $swarms = $counts === false ? 0 : intval($counts['torrents']);

    // Page window. Offset arrives from the query string; a non-numeric value
    // collapses to 0. The limit is an operator setting.
    $limit = max(1, intval($settings['admin_peers_limit']));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));

    require_once __DIR__.'/../model/peers.select.all.php';
    $peers = peers_select_all($connection, $settings, $limit, $offset);

    // Tag each peer with a client label derived transiently from peer_id — it is
    // never stored, matching stats_client_detect's privacy contract.
    require_once __DIR__.'/../functions/stats.client.detect.php';
    $tagged = [];
    foreach ($peers as $peer) {
        $tagged[] = $peer + ['client' => stats_client_detect($peer['peer_id'])];
    }

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.peers.php';

    return view_admin_peers_html($settings, $tagged, $total, $swarms, $offset, $limit, $csrf_token);
}
