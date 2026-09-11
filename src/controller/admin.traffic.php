<?php

declare(strict_types=1);

////	admin_traffic_controller
// Renders the admin Traffic page: a time series of traffic served, and a table
// of it per torrent, with a metric toggle between the two figures the tracker
// actually holds — the all-time estimate derived from the events ledger, and
// the live swarm's client-reported byte counters. Read-only. Dispatched by
// admin_panel_controller() for page=traffic.
//
// ?metric selects the table's measure and ?days the chart window; both are
// narrowed to known values here rather than trusted, and the models clamp them
// again.

/** @param PhoenixSettings $settings */
function admin_traffic_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../model/db.tables.installed.php';
    $tables_installed = db_tables_installed($connection, $settings);

    $metric = ($_GET['metric'] ?? '') === 'peers' ? 'peers' : 'events';

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
    $torrents = [];
    if ($tables_installed) {
        require_once __DIR__.'/../model/stats.traffic.series.php';
        require_once __DIR__.'/../model/torrents.traffic.php';
        $series = stats_traffic_series($connection, $settings, $windows[$window]['days'], $windows[$window]['bucket']);
        $torrents = torrents_traffic($connection, $settings, $metric);
    }

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.traffic.php';

    return view_admin_traffic_html($settings, $series, $torrents, $metric, $window, $windows, $csrf_token);
}
