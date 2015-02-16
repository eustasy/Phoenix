<?php

// This file defines all the functions we will need.
// Since it defines things, we need to make sure it isn't loaded twice.
require_once __DIR__.'/phoenix.php';

// IF STATS
if ( isset($_GET['stats']) ) {
	require_once __DIR__.'/function.tracker.stats.php';
	tracker_stats();
// END IF STATS

// IF NOT STATS
} else {

	// IF SCRAPE
	if (
		////	info_hash
		// Optional
		// 40 Characters
		// Hexadecimal
		////	IF Allowed
		// Tracker is Open
		// OR
		// Torrent is Allowed
		isset($_GET['info_hash']) &&
		strlen($_GET['info_hash']) == 40 &&
		(
			$settings['open_tracker'] ||
			in_array($_GET['info_hash'], $torrents)
		)
	) {
		// Perform a Scrape on the torrent.
		require_once __DIR__.'/function.torrent.scrape.php';
		torrent_scrape();
	// END IF SCRAPE

	// IF FULL SCRAPE
	} else if ( $settings['full_scrape'] ) {
		// Scrape the full tracker.
		require_once __DIR__.'/function.tracker.scrape.php';
		tracker_scrape();
	// END IF FULL SCRAPE

	// IF NOT ALLOWED TO SCRAPE
	} else {

		// IF ERROR TORRENT
		if ( isset($_GET['info_hash']) ) {
			tracker_error('Torrent is not allowed.');
		// END IF ERROR TORRENT

		// IF ERROR TRACKER
		} else {
			tracker_error('Tracker scraping is not allowed.');
		} // END IF ERROR TRACKER

	} // END IF NOT ALLOWED TO SCRAPE

} // END IF NOT STATS