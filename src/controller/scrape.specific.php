<?php

declare(strict_types=1);

////	scrape_specific_controller
// Handles a BEP 15 scrape request for one or more specific info_hashes.
// Caller is responsible for verifying the request is authorised (open
// tracker, or all hashes appear in $allowed_torrents) before invoking.
// Returns the rendered XML/JSON/bencode body. On scrape failure calls
// tracker_error() (which exits).

function scrape_specific_controller(mysqli $connection, array $settings, array $valid_info_hashes): string
{
    require_once __DIR__.'/../functions/scrape.build.where.clause.php';
    require_once __DIR__.'/../functions/scrape.initialize.results.php';
    require_once __DIR__.'/../functions/scrape.merge.results.php';
    require_once __DIR__.'/../model/peers.scrape.php';
    require_once __DIR__.'/../model/torrents.scrape.php';

    // Pre-initialize the result map so requested-but-unknown hashes still
    // get a zero-count reply rather than being omitted from the response.
    $where = scrape_build_where_clause($valid_info_hashes);
    $scrape = scrape_initialize_results($valid_info_hashes);
    $peers = peers_scrape($connection, $settings, $where);
    $torrents = torrents_scrape($connection, $settings, $where);

    if (! $peers || ! $torrents) {
        tracker_error('Unable to scrape for that torrent.');
    }

    $scrape = scrape_merge_results($peers, $torrents, $scrape);

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
