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
    $torrent_cards = [];
    $count_cards = [];
    $peer_cards = [];
    $clients = [];
    $traffic = [];
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

        // Mini-table cards. Each is a short ranked list that links into the
        // listing it summarises, so the dashboard is a way in rather than a
        // dead end. All are cheap aggregates over the same two tables.
        require_once __DIR__.'/../model/torrents.top.php';
        require_once __DIR__.'/../model/peers.client.counts.php';
        $torrent_cards = [
            'seeded' => torrents_top($connection, $settings, 'seeders'),
            'leeched' => torrents_top($connection, $settings, 'leechers'),
            'trouble' => torrents_top($connection, $settings, 'trouble'),
            'traffic' => torrents_top($connection, $settings, 'traffic'),
        ];
        $count_cards = ['clients' => peers_client_counts($connection, $settings)];

        // Peers ranked by bytes moved — the peer-side counterpart to the
        // torrent cards, which rank torrents by how many peers they have.
        require_once __DIR__.'/../model/peers.top.php';
        $peer_cards = [
            'seeders' => peers_top($connection, $settings, 'seeders'),
            'leechers' => peers_top($connection, $settings, 'leechers'),
        ];

        // Countries reuse the Geography page's aggregation rather than a second
        // copy of it; it returns [] when geo is not configured, and the card
        // then simply does not render.
        require_once __DIR__.'/../model/peers.geo.counts.php';
        $count_cards['countries'] = peers_geo_counts($connection, $settings);

        // Same aggregation as the Top Clients card, regrouped family/version
        // for the stacked chart — no extra query.
        require_once __DIR__.'/../model/peers.client.breakdown.php';
        $clients = peers_client_breakdown($connection, $settings);

        // A short window for the dashboard: enough to show the shape without
        // the cost of the Traffic page's longer views.
        require_once __DIR__.'/../model/stats.traffic.series.php';
        $traffic = stats_traffic_series($connection, $settings, 30, 86400);
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
        $torrent_cards,
        $count_cards,
        $peer_cards,
        $clients,
        $traffic,
    );
}
