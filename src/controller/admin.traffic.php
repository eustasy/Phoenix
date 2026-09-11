<?php

declare(strict_types=1);

////	admin_traffic_controller
// Renders the admin Traffic page: a time series of traffic served, and a table
// of it per torrent, with a metric toggle between the two figures the tracker
// actually holds — the all-time estimate derived from the events ledger, and
// the live swarm's client-reported byte counters. Read-only. Dispatched by
// admin_panel_controller() for page=traffic.
//
// ?metric selects both the table's measure and which chart is drawn — the
// ledger-derived time series for the all-time estimate, the busiest peers for
// the live swarm, since the live counters have no history. ?days picks the
// series window. Both are narrowed to known values here rather than trusted,
// and the models clamp them again.

/** @param PhoenixSettings $settings */
function admin_traffic_controller(mysqli $connection, array $settings): string
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

    $series = [];
    $swarm = [];
    $torrents = [];
    if ($tables_installed) {
        require_once __DIR__.'/../model/torrents.traffic.php';
        $torrents = torrents_traffic($connection, $settings, $metric);

        if ($metric === 'peers') {
            // The live counters have no history to plot — they are cumulative
            // since each client started and vanish when a peer leaves — so the
            // Active peers metric gets the busiest peers instead of a time series.
            require_once __DIR__.'/../model/peers.top.php';
            require_once __DIR__.'/../functions/stats.client.detect.php';
            foreach (peers_top($connection, $settings, 'traffic', 10) as $peer) {
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
            require_once __DIR__.'/../model/stats.traffic.series.php';
            $series = stats_traffic_series($connection, $settings, $windows[$window]['days'], $windows[$window]['bucket']);
        }
    }

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.traffic.php';

    return view_admin_traffic_html($settings, $series, $torrents, $metric, $window, $windows, $csrf_token, $swarm);
}
