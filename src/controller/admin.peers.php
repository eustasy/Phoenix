<?php

declare(strict_types=1);

////	admin_peers_controller
// Renders the admin global Peers page: a page of peers across every swarm, each
// tagged with a detected client label, newest-seen first. The swarm-wide totals
// (active peers, distinct swarms) come from the same aggregation the dashboard
// uses; the rows are paged via admin_peers_rows and an ?offset, and searched,
// filtered and sorted server-side via ?q / ?state / ?sort / ?dir, and narrowed
// to one swarm by ?info_hash — which is the per-torrent drill-down, served by
// this same paged view rather than a separate unpaged one. Dispatched by
// admin_panel_controller() for page=peers.

/** @param PhoenixSettings $settings */
function admin_peers_controller(mysqli $connection, array $settings): string
{
    // Swarm-wide totals: active peers (= seeders + leechers) and the distinct
    // swarm count, reusing the dashboard/scrape aggregation.
    require_once __DIR__.'/../model/stats.peers.php';
    $counts = stats_fetch_peer_counts($connection, $settings);
    $swarms = $counts === false ? 0 : intval($counts['torrents']);

    // Page window. Offset arrives from the query string; a non-numeric value
    // collapses to 0. The limit is an operator setting.
    $limit = max(1, intval($settings['admin_peers_rows']));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));

    // Search, filter and sort are applied in SQL rather than in the browser, so
    // they see every peer and not just the rendered page. All four values are
    // untrusted: the model binds $search and whitelists $sort/$dir, and $state
    // is narrowed to the two valid flags here.
    $search = is_string($_GET['q'] ?? null) ? trim((string) $_GET['q']) : '';
    $state = isset($_GET['state']) && ($_GET['state'] === '0' || $_GET['state'] === '1')
        ? (int) $_GET['state']
        : -1;
    $sort = is_string($_GET['sort'] ?? null) ? (string) $_GET['sort'] : 'updated';
    $dir = ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc';

    // ?info_hash narrows the listing to one swarm — the per-torrent drill-down
    // is this same paged view with a filter, not a separate unpaged one that
    // would render every peer of a large swarm at once. Validated to 40-char hex
    // before it is bound, like every other info_hash entering the tracker.
    $info_hash = '';
    $torrent_name = null;
    if (isset($_GET['info_hash']) && $_GET['info_hash'] !== '') {
        require_once __DIR__.'/../functions/sanitize.maybe_binary_to_hex.php';
        $raw = $_GET['info_hash'];
        $hex = maybe_binary_to_hex(is_string($raw) ? $raw : '');
        if ($hex === false || strlen($hex) !== 40) {
            tracker_error('Info Hash is invalid.');
        }
        $info_hash = $hex;

        // Registry name for the header; null when the swarm has no torrents row.
        require_once __DIR__.'/../model/torrent.select.one.php';
        $torrent = torrent_select_one($connection, $settings, $info_hash);
        $torrent_name = ($torrent !== false && is_string($torrent['name'])) ? $torrent['name'] : null;
    }

    require_once __DIR__.'/../model/peers.select.all.php';
    $peers = peers_select_all($connection, $settings, $limit, $offset, $search, $state, $sort, $dir, $info_hash);

    // Resolve this page's addresses to countries in one batch, so the reader is
    // opened once rather than per row. Like the client label below, the country
    // is derived for this render and never written back — the addresses it is
    // derived from are stored, as a tracker's swarm index has to be.
    require_once __DIR__.'/../functions/stats.geo.lookup.batch.php';
    $ips = [];
    foreach ($peers as $peer) {
        $ip = $peer['ipv4'] !== '' ? $peer['ipv4'] : $peer['ipv6'];
        if ($ip !== '') {
            $ips[] = $ip;
        }
    }
    $geo = stats_geo_lookup_batch($settings, $ips);

    // Tag each peer with a client label derived transiently from peer_id — it is
    // never stored, matching stats_client_detect's privacy contract.
    require_once __DIR__.'/../functions/stats.client.detect.php';
    $tagged = [];
    foreach ($peers as $peer) {
        $ip = $peer['ipv4'] !== '' ? $peer['ipv4'] : $peer['ipv6'];
        $tagged[] = $peer + [
            'client' => stats_client_detect($peer['peer_id']),
            'country' => $geo[$ip]['country'] ?? '',
            'country_name' => $geo[$ip]['name'] ?? '',
        ];
    }

    // The pager counts what the filter matched, not the whole table, or a
    // filtered list would page against an unfiltered total and trail off into
    // empty pages.
    require_once __DIR__.'/../model/peers.count.php';
    $total = peers_count($connection, $settings, $search, $state, $info_hash);

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.peers.php';

    return view_admin_peers_html($settings, $tagged, $total, $swarms, $offset, $limit, $csrf_token, $search, $state, $sort, $dir, $info_hash, $torrent_name);
}
