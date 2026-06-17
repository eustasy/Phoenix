<?php

declare(strict_types=1);

////	BitTorrent Scrape Endpoint (BEP 48) + Tracker Stats

// Allow cross-origin reads — browser-based clients scrape here. Sent before
// bootstrap so error responses carry it too. Scoped to the public read endpoints
// (announce/scrape/index); the admin panel deliberately omits it.
header('Access-Control-Allow-Origin: *');

require_once __DIR__.'/../src/phoenix.php';

require_once __DIR__.'/../src/functions/sanitize.tracker.php';
$peer = sanitize_tracker_params();

////	Stats Mode
if (isset($_GET['stats'])) {
    require_once __DIR__.'/../src/controller/scrape.stats.php';
    echo scrape_stats_controller($connection, $settings);
    exit;
}

// Drop any info_hashes that failed sanitization (maybe_binary_to_hex returns
// false for those) so they cannot reach the SQL layer or seed result rows
// keyed by the literal value false.
$valid_info_hashes = array_values(array_filter($peer['info_hashes']));

////	Scrape Mode (specific torrents)
// Open trackers accept any info_hash; closed trackers silently drop the
// disallowed entries and reply with whatever's left. If nothing's left
// after filtering we error out rather than falling through to the
// full-scrape branch — that would leak every tracked torrent to a user
// who wasn't allowed to see any of the specific ones they requested.
if (! empty($valid_info_hashes)) {
    if (! $settings['open_tracker']) {
        require_once __DIR__.'/../src/functions/tracker.filter.info.hashes.php';
        $valid_info_hashes = tracker_filter_info_hashes($valid_info_hashes, $allowed_torrents);
        if (empty($valid_info_hashes)) {
            tracker_error('Torrent is not allowed.');
        }
    }
    require_once __DIR__.'/../src/controller/scrape.specific.php';
    echo scrape_specific_controller($connection, $settings, $valid_info_hashes);
    exit;
}

////	Full Scrape Mode
if ($settings['full_scrape']) {
    require_once __DIR__.'/../src/controller/scrape.full.php';
    echo scrape_full_controller($connection, $settings);
    exit;
}

////	Not Allowed
// Reached only when the request supplied no valid info_hashes and
// full_scrape is disabled. The "Torrent is not allowed." case is now
// handled inline in the specific-scrape block above.
tracker_error('Tracker scraping is not allowed.');
