<?php

// This file defines all the classes and functions we will need.
// Since it defines things, we need to make sure it isn't loaded twice.
require_once __DIR__.'/phoenix.php';

// Open Database
phoenix::open();

// IF STATS
if ( isset($_GET['stats']) ) {

	// Display Statistics
	phoenix::stats();

// END IF STATS
// IF NOT STATS
} else {

	// IF MAGIC QUOTES
	if ( get_magic_quotes_gpc() ) {
		// Strip auto-escaped data.
		$_GET['info_hash'] = stripslashes($_GET['info_hash']);
	} // END IF MAGIC QUOTES

	// 20-bytes - info_hash
	// sha-1 hash of torrent being tracked
	if (
		!isset($_GET['info_hash']) ||
		strlen($_GET['info_hash']) != 20
	) {
		// full scrape disabled
		if (!$_SERVER['tracker']['full_scrape']) exit;
		// full scrape enabled
		else unset($_GET['info_hash']);
	}

	// TODO
	// Check torrent is allowed when private.

	// Perform a Scrape
	phoenix::scrape();

} // END IF NOT STATS

// Close Database
phoenix::close();