<?php

declare(strict_types=1);

////	scrape_stats_controller
// Handles `?stats` requests on the scrape endpoint. Loads the per-table
// helpers it needs, fetches peer counts + download totals, merges them, and
// dispatches to the XML/JSON/HTML view based on $_GET flags. Returns the
// rendered body. On data-fetch failure calls tracker_error() (which exits).

/** @param PhoenixSettings $settings */
function scrape_stats_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../model/stats.peers.php';
    require_once __DIR__.'/../model/stats.downloads.php';
    require_once __DIR__.'/../functions/stats.merge.php';

    $peer_counts = stats_fetch_peer_counts($connection, $settings);
    $download_totals = stats_fetch_download_totals($connection, $settings);
    $stats = stats_merge($peer_counts, $download_totals);

    if (! $stats) {
        tracker_error('Unable to get stats.');
    }

    if (isset($_GET['xml'])) {
        require_once __DIR__.'/../views/xml.stats.php';
        header('Content-Type: text/xml');

        return view_stats_xml($stats, $settings);
    }
    if (isset($_GET['json'])) {
        require_once __DIR__.'/../views/json.stats.php';
        header('Content-Type: application/json');

        return view_stats_json($stats, $settings);
    }
    require_once __DIR__.'/../views/html.stats.php';

    return view_stats_html($stats, $settings);
}
