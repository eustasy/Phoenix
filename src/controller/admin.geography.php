<?php

declare(strict_types=1);

////	admin_geography_controller
// Renders the admin Geography page from real data. Builds the per-country maps
// for the metrics that have a usable source and hands them to the view, which
// renders the choropleth (or a "geo isn't configured" state when none are
// available). Dispatched by admin_panel_controller() for page=geography.
//
// Metrics, each included only when meaningful:
//   * peers     — Active peers by country: a live geo lookup of the peers
//                 table, available only when geo is configured (stats_geo on,
//                 geoip2 present, readable .mmdb). Included whenever geo is
//                 configured, even with zero current peers.
//   * downloads — Completed downloads by country: from the events ledger's
//                 stored coarse codes. Shown whenever geo is configured (so it
//                 sits alongside peers and fills in as completions are logged),
//                 or whenever the ledger already carries geo-tagged completions.

/** @param PhoenixSettings $settings */
function admin_geography_controller(mysqli $connection, array $settings): string
{
    $metrics = [];

    // Active peers by country — only when geo is configured (same gate as
    // stats_geo_lookup); peers_geo_counts() returns [] otherwise, but we still
    // surface the live metric so a configured tracker with no current peers
    // shows an empty map rather than the not-configured state.
    $geo_ready = class_exists(\GeoIp2\Database\Reader::class)
        && $settings['stats_geo'] === true
        && is_readable($settings['stats_geo_database']);
    if ($geo_ready) {
        require_once __DIR__.'/../model/peers.geo.counts.php';
        $metrics['peers'] = peers_geo_counts($connection, $settings);
    }

    // Completed downloads by country. Shown when geo is configured (so it
    // appears next to peers and fills in as completions are geo-tagged), or
    // when the ledger already holds geo-tagged completions even if geo was
    // since turned off.
    require_once __DIR__.'/../model/events.geo.counts.php';
    $downloads = events_geo_counts($connection, $settings);
    if ($geo_ready || $downloads !== []) {
        $metrics['downloads'] = $downloads;
    }

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.geography.php';

    return view_admin_geography_html($settings, $metrics, $csrf_token);
}
