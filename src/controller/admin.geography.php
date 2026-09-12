<?php

declare(strict_types=1);

////	admin_geography_controller
// Renders the admin Geography page from real data.
//
// One metric is computed per request — the one asked for by ?metric, or the
// first available — and the others are offered as links. Each of these
// aggregations reads the whole events ledger, so computing all of them to fill
// a client-side toggle meant paying for two maps nobody had asked to see.
//
// Metrics, each offered only when it has a usable source:
//   * peers     — Active peers by country: a live geo lookup of the peers
//                 table, available only when geo is configured (stats_geo on,
//                 reader present, readable .mmdb). Offered whenever geo is
//                 configured, even with zero current peers.
//   * downloads — Completed downloads by country, from the events ledger's
//                 stored coarse codes.
//   * bandwidth   — The same completions weighted by torrent size: one dataset
//                 read two ways, so it is offered on the same condition as
//                 downloads, and carries the same estimate caveat.
//
// The ledger metrics are offered when geo is configured (so they sit alongside
// peers and fill in as completions are logged), or when the ledger already
// holds geo-tagged completions even if geo has since been turned off. That
// second test is a LIMIT 1 seek, not a full aggregation.

/** @param PhoenixSettings $settings */
function admin_geography_controller(mysqli $connection, array $settings): string
{
    $geo_ready = class_exists(\MaxMind\Db\Reader::class)
        && $settings['stats_geo'] === true
        && is_readable($settings['stats_geo_database']);

    require_once __DIR__.'/../model/events.geo.any.php';
    $ledger_ready = $geo_ready || events_geo_any($connection, $settings);

    // Which metrics the page can offer, in display order.
    $available = [];
    if ($geo_ready) {
        $available[] = 'peers';
    }
    if ($ledger_ready) {
        $available[] = 'downloads';
        $available[] = 'bandwidth';
    }

    // The requested metric, or the first available. An unknown or unavailable
    // one falls back rather than rendering an empty map.
    $requested = is_string($_GET['metric'] ?? null) ? (string) $_GET['metric'] : '';
    $metric = in_array($requested, $available, true) ? $requested : ($available[0] ?? '');

    // Only the selected metric is computed.
    $values = [];
    if ($metric === 'peers') {
        require_once __DIR__.'/../model/peers.geo.counts.php';
        $values = peers_geo_counts($connection, $settings);
    } elseif ($metric === 'downloads') {
        require_once __DIR__.'/../model/events.geo.counts.php';
        $values = events_geo_counts($connection, $settings);
    } elseif ($metric === 'bandwidth') {
        require_once __DIR__.'/../model/events.geo.bandwidth.php';
        $values = events_geo_bandwidth($connection, $settings);
    }

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.geography.php';

    return view_admin_geography_html($settings, $metric, $values, $available, $csrf_token);
}
