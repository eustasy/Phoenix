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

	// IF MAGIC QUOTES
	if (
		isset($_GET['info_hash']) &&
		get_magic_quotes_gpc()
	) {
		// Strip auto-escaped data.
		$_GET['info_hash'] = stripslashes($_GET['info_hash']);
	} // END IF MAGIC QUOTES

	// IF SCRAPE
	if (
		// info_hash
		// sha-1 hash of torrent being tracked
		// sometimes binary, sometimes not.
		isset($_GET['info_hash']) &&
		(
			// Open Tracker.
			$settings['open_tracker'] ||
			// BINARY is allowed.
			in_array(bin2hex($_GET['info_hash']), $torrents) ||
			// HEX is allowed.
			in_array($_GET['info_hash'], $torrents)
		)
	) {

		// Perform a Scrape on the torrent.
		require_once __DIR__.'/function.torrent.scrape.php';
		torrent_scrape($_GET['info_hash']);

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