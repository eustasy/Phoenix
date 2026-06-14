<?php

declare(strict_types=1);

////	admin_dashboard_page
// Renders the admin panel's Dashboard page: the tracker-statistics overview
// (peer/torrent/download aggregates and the last-run maintenance timestamps)
// plus the post-install confirmation banner. Read-only — the maintenance
// actions live on their own pages (Server Support, Utilities, Add Torrent).
// Dispatched by admin_panel_controller() for page=dashboard.

/** @param PhoenixSettings $settings */
function admin_dashboard_page(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../model/db.tables.installed.php';
    $tables_installed = db_tables_installed($connection, $settings);

    $stats = false;
    $tasks = [];
    if ($tables_installed) {
        // Surface the already-computed tracker stats (same aggregation the
        // ?stats scrape uses) plus the total registered-torrent count and the
        // maintenance task timestamps.
        require_once __DIR__.'/../model/stats.peers.php';
        require_once __DIR__.'/../model/stats.downloads.php';
        require_once __DIR__.'/../functions/stats.merge.php';
        $stats = stats_merge(
            stats_fetch_peer_counts($connection, $settings),
            stats_fetch_download_totals($connection, $settings),
        );
        if ($stats !== false) {
            require_once __DIR__.'/../model/torrents.count.php';
            $stats['registered'] = torrents_count($connection, $settings);
        }

        require_once __DIR__.'/../model/tasks.select.php';
        $tasks = tasks_select($connection, $settings);
    }

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.php';

    return view_admin_html(
        $settings,
        $tables_installed,
        isset($_GET['installed']),
        $csrf_token,
        $stats,
        $tasks,
    );
}
