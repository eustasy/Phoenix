<?php

// This file defines all the functions we will need.
// Since it defines things, we need to make sure it isn't loaded twice.
require_once __DIR__.'/_phoenix.php';
require_once $settings['onces'].'once.sanitize.tracker.php';

// Drop any info_hashes that failed sanitization (maybe_binary_to_hex returns
// false for those) so they cannot reach the SQL layer or seed result rows
// keyed by the literal value false.
$peer['info_hashes'] = array_values(array_filter($peer['info_hashes']));

// IF STATS
if ( isset($_GET['stats']) ) {
		require_once $settings['onces'].'once.stats.tracker.php';
// END IF STATS

// IF NOT STATS
} else {
	// IF SCRAPE
	if (!empty($peer['info_hashes'])) {
		if (!$settings['open_tracker']) {
			require_once $settings['functions'].'function.tracker.filter.info.hashes.php';
			$peer['info_hashes'] = tracker_filter_info_hashes($peer['info_hashes'], $allowed_torrents);
			if (empty($peer['info_hashes'])) {
				tracker_error('Torrent is not allowed.');
			}
		}
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
