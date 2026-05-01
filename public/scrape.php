<?php

// This file defines all the functions we will need.
// Since it defines things, we need to make sure it isn't loaded twice.
require_once __DIR__.'/../src/phoenix.php';

require_once $settings['functions'].'function.sanitize.tracker.php';
$peer = sanitize_tracker_params();

// IF STATS
if ( isset($_GET['stats']) ) {
	require_once $settings['functions'].'function.stats.fetch.peer.counts.php';
	require_once $settings['functions'].'function.stats.fetch.download.totals.php';
	require_once $settings['functions'].'function.stats.merge.php';

	$peer_counts = stats_fetch_peer_counts($connection, $settings);
	$download_totals = stats_fetch_download_totals($connection, $settings);
	$stats = stats_merge($peer_counts, $download_totals);

	if (!$stats) {
		tracker_error('Unable to get stats.');
	}

	// XML
	if ( isset($_GET['xml']) ) {
		require_once $settings['functions'].'function.stats.render.xml.php';
		stats_render_xml($stats, $settings);

	// JSON
	} else if ( isset($_GET['json']) ) {
		require_once $settings['functions'].'function.stats.render.json.php';
		stats_render_json($stats, $settings);

	// HTML
	} else {
		require_once $settings['functions'].'function.stats.render.html.php';
		stats_render_html($stats, $settings);
	}
// END IF STATS

// IF NOT STATS
} else {
	// IF SCRAPE
	if (
		$peer['info_hash'] &&
		(
			$settings['open_tracker'] ||
			in_array($peer['info_hash'], $allowed_torrents)
		)
	) {
		// Perform a Scrape on the torrent.
		require_once $settings['onces'].'once.scrape.torrent.php';
	// END IF SCRAPE

	// IF FULL SCRAPE
	} else if ( $settings['full_scrape'] ) {
		// Scrape the full tracker.
		require_once $settings['onces'].'once.scrape.tracker.php';
	// END IF FULL SCRAPE

	// IF NOT ALLOWED TO SCRAPE
	} else {
		// IF ERROR TORRENT
		if ( isset($peer['info_hash']) ) {
			tracker_error('Torrent is not allowed.');
		// END IF ERROR TORRENT

		// IF ERROR TRACKER
		} else {
			tracker_error('Tracker scraping is not allowed.');
		} // END IF ERROR TRACKER

	} // END IF NOT ALLOWED TO SCRAPE

} // END IF NOT STATS
