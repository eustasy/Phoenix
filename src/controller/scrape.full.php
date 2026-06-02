<?php

declare(strict_types=1);

////	scrape_full_controller
// Handles a BEP 15 full-scrape request (no info_hash supplied, full_scrape
// enabled). Caller is responsible for checking $settings['full_scrape']
// before invoking. Returns the rendered XML/JSON/bencode body. On scrape
// failure calls tracker_error() (which exits).

/** @param array<string, mixed> $settings */
function scrape_full_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../functions/scrape.merge.results.php';
    require_once __DIR__.'/../model/peers.scrape.all.php';
    require_once __DIR__.'/../model/torrents.scrape.all.php';

    $peers = peers_scrape_all($connection, $settings);
    $torrents = torrents_scrape_all($connection, $settings);

    if (! $peers || ! $torrents) {
        tracker_error('Unable to scrape for that torrent.');
    }

    $scrape = scrape_merge_results($peers, $torrents);

    if (isset($_GET['xml'])) {
        require_once __DIR__.'/../views/xml.scrape.php';
        header('Content-Type: text/xml');

        return view_scrape_xml($scrape);
    }
    if (isset($_GET['json'])) {
        require_once __DIR__.'/../views/json.scrape.php';
        header('Content-Type: application/json');

        return view_scrape_json($scrape);
    }
    require_once __DIR__.'/../views/bencode.scrape.php';
    header('Content-Type: text/plain; charset=ISO-8859-1');

    return view_scrape_bencode($scrape);
}
