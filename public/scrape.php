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
		require_once $settings['functions'].'function.scrape.build.where.clause.php';
		require_once $settings['functions'].'function.scrape.initialize.results.php';
		require_once $settings['functions'].'function.scrape.query.peers.php';
		require_once $settings['functions'].'function.scrape.query.torrents.php';

		// BEP 15 allows a single scrape request to carry multiple info_hashes.
		// Build WHERE clause and zero-initialise $scrape entries for all requested hashes
		// so missing torrents still get a response rather than being silently omitted.
		$where    = scrape_build_where_clause($peer['info_hashes']);
		$scrape   = scrape_initialize_results($peer['info_hashes']);
		$peers    = scrape_query_peers($connection, $settings, $where);
		$torrents = scrape_query_torrents($connection, $settings, $where);

		if ( !$peers || !$torrents ) {
			tracker_error('Unable to scrape for that torrent.');
		}

		require_once $settings['functions'].'function.scrape.output.php';
		scrape_output($scrape, $peers, $torrents);
	// END IF SCRAPE

	// IF FULL SCRAPE
	} else if ( $settings['full_scrape'] ) {
		// Scrape the full tracker.
		require_once $settings['functions'].'function.scrape.query.all.peers.php';
		require_once $settings['functions'].'function.scrape.query.all.torrents.php';

		// Full scrape: no WHERE clause, returns all tracked torrents.
		// $scrape is not pre-initialised here; scrape_output builds it from the results.
		$peers    = scrape_query_all_peers($connection, $settings);
		$torrents = scrape_query_all_torrents($connection, $settings);

		if ( !$peers || !$torrents ) {
			tracker_error('Unable to scrape for that torrent.');
		}

		require_once $settings['functions'].'function.scrape.output.php';
		scrape_output(array(), $peers, $torrents);
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
