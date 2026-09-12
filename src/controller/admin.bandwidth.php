<?php

declare(strict_types=1);

////	admin_bandwidth_controller
// Renders the admin Bandwidth page: a time series of bandwidth served, and a table
// of it per torrent, with a metric toggle between the two figures the tracker
// actually holds — the all-time estimate derived from the events ledger, and
// the live swarm's client-reported byte counters. Read-only. Dispatched by
// admin_panel_controller() for page=bandwidth.
//
// ?metric selects both the table's measure and which chart is drawn — the
// ledger-derived time series for the all-time estimate, the busiest peers for
// the live swarm, since the live counters have no history. ?days picks the
// series window. Both are narrowed to known values here rather than trusted,
// and the models clamp them again.
//
// The table below the chart is paged, searched and sorted in SQL via ?q /
// ?info_hash / ?sort / ?dir / ?offset, the same shape the Peers and Torrents
// listings use. ?info_hash narrows to one torrent, so a row elsewhere can link
// here for that torrent's bandwidth without a second view to keep in step.

/** @param PhoenixSettings $settings */
function admin_bandwidth_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../model/db.tables.installed.php';
    $tables_installed = db_tables_installed($connection, $settings);

    // Active peers by default, matching the Clients page: what the tracker is
    // doing now is the more common question, and it is also the cheaper one —
    // the all-time view scans the whole events ledger.
    $metric = ($_GET['metric'] ?? '') === 'events' ? 'events' : 'peers';

    // Windows the chart can show. Daily buckets for the shorter ones; the
    // all-time view buckets monthly, or a long-lived ledger would draw
    // thousands of points nobody can read.
    $windows = [
        '30' => ['days' => 30, 'bucket' => 86400, 'label' => '30 days'],
        '90' => ['days' => 90, 'bucket' => 86400, 'label' => '90 days'],
        '365' => ['days' => 365, 'bucket' => 604800, 'label' => 'Year'],
        'all' => ['days' => 4000, 'bucket' => 2592000, 'label' => 'All time'],
    ];
    $window = is_string($_GET['days'] ?? null) && isset($windows[$_GET['days']])
        ? (string) $_GET['days']
        : '90';

    // All untrusted: the model binds $search and whitelists $sort/$dir.
    $limit = max(1, intval($settings['admin_bandwidth_rows']));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    $search = is_string($_GET['q'] ?? null) ? trim((string) $_GET['q']) : '';
    $sort = is_string($_GET['sort'] ?? null) ? (string) $_GET['sort'] : 'bandwidth';
    $dir = ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc';

    // Validated to 40-char hex before it is bound, like every other info_hash
    // entering the tracker.
    $info_hash = '';
    if (isset($_GET['info_hash']) && $_GET['info_hash'] !== '') {
        require_once __DIR__.'/../functions/sanitize.maybe_binary_to_hex.php';
        $raw = $_GET['info_hash'];
        $hex = maybe_binary_to_hex(is_string($raw) ? $raw : '');
        if ($hex === false || strlen($hex) !== 40) {
            tracker_error('Info Hash is invalid.');
        }
        $info_hash = $hex;
    }

    $series = [];
    $swarm = [];
    $torrents = [];
    $total = 0;
    if ($tables_installed) {
        require_once __DIR__.'/../model/torrents.bandwidth.php';
        $torrents = torrents_bandwidth($connection, $settings, $metric, $limit, $offset, $search, $info_hash, $sort, $dir);

        // The pager counts what the filter matched, not the whole table, or a
        // filtered list would page against an unfiltered total and trail off
        // into empty pages.
        require_once __DIR__.'/../model/torrents.count.php';
        $total = torrents_count($connection, $settings, $search, -1, $info_hash);

        if ($metric === 'peers') {
            // The live counters have no history to plot — they are cumulative
            // since each client started and vanish when a peer leaves — so the
            // Active peers metric gets the busiest peers instead of a time series.
            require_once __DIR__.'/../model/peers.top.php';
            require_once __DIR__.'/../functions/stats.client.detect.php';
            foreach (peers_top($connection, $settings, 'bandwidth', 10) as $peer) {
                $swarm[] = [
                    'label' => $peer['address'],
                    // Derived transiently for the tooltip, never stored, as
                    // everywhere else the client label is shown.
                    'client' => stats_client_detect($peer['peer_id']),
                    'torrent' => $peer['name'],
                    'uploaded' => $peer['uploaded'],
                    'downloaded' => $peer['downloaded'],
                ];
            }
        } else {
            require_once __DIR__.'/../model/stats.bandwidth.series.php';
            $series = stats_bandwidth_series($connection, $settings, $windows[$window]['days'], $windows[$window]['bucket']);
        }
    }

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.bandwidth.php';

    return view_admin_bandwidth_html($settings, $series, $torrents, $metric, $window, $windows, $csrf_token, $swarm, $total, $offset, $limit, $search, $info_hash, $sort, $dir);
}
