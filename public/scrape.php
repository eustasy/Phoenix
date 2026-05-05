<?php

////	BitTorrent Scrape Endpoint (BEP 15) + Tracker Stats
require_once __DIR__.'/../src/phoenix.php';

require_once $settings['functions'].'function.sanitize.tracker.php';
$peer = sanitize_tracker_params();

if (!$settings['open_tracker']) {
	require_once $settings['functions'].'function.tracker.validate.info.hashes.php';
}

////	Stats Mode
if (isset($_GET['stats'])) {
	require_once $settings['model'].'stats.peers.php';
	require_once $settings['model'].'stats.downloads.php';
	require_once $settings['functions'].'function.stats.merge.php';

	$peer_counts = stats_fetch_peer_counts($connection, $settings);
	$download_totals = stats_fetch_download_totals($connection, $settings);
	$stats = stats_merge($peer_counts, $download_totals);

	if (!$stats) {
		tracker_error('Unable to get stats.');
	}

	if (isset($_GET['xml'])) {
		require_once $settings['views'].'xml.stats.php';
		header('Content-Type: text/xml');
		echo view_stats_xml($stats, $settings);
	} elseif (isset($_GET['json'])) {
		require_once $settings['views'].'json.stats.php';
		header('Content-Type: application/json');
		echo view_stats_json($stats, $settings);
	} else {
		require_once $settings['views'].'html.stats.php';
		echo view_stats_html($stats, $settings);
	}
	exit;
}

// Drop any info_hashes that failed sanitization (maybe_binary_to_hex returns
// false for those) so they cannot reach the SQL layer or seed result rows
// keyed by the literal value false.
$valid_info_hashes = array_values(array_filter($peer['info_hashes']));

////	Scrape Mode (specific torrents)
if (
	!empty($valid_info_hashes) &&
	(
		$settings['open_tracker'] ||
		tracker_validate_info_hashes($valid_info_hashes, $allowed_torrents)
	)
) {
	require_once $settings['functions'].'function.scrape.build.where.clause.php';
	require_once $settings['functions'].'function.scrape.initialize.results.php';
	require_once $settings['functions'].'function.scrape.merge.results.php';
	require_once $settings['model'].'peers.scrape.php';
	require_once $settings['model'].'torrents.scrape.php';

	// BEP 15 allows multiple info_hashes per request.
	// Pre-initialize results so missing torrents get zero counts instead of being omitted.
	$where = scrape_build_where_clause($valid_info_hashes);
	$scrape = scrape_initialize_results($valid_info_hashes);
	$peers = peers_scrape($connection, $settings, $where);
	$torrents = torrents_scrape($connection, $settings, $where);

	if (!$peers || !$torrents) {
		tracker_error('Unable to scrape for that torrent.');
	}

	$scrape = scrape_merge_results($peers, $torrents, $scrape);

	// Render
	if (isset($_GET['xml'])) {
		require_once $settings['views'].'xml.scrape.php';
		header('Content-Type: text/xml');
		echo view_scrape_xml($scrape);
	} elseif (isset($_GET['json'])) {
		require_once $settings['views'].'json.scrape.php';
		header('Content-Type: application/json');
		echo view_scrape_json($scrape);
	} else {
		require_once $settings['views'].'bencode.scrape.php';
		header('Content-Type: text/plain; charset=ISO-8859-1');
		echo view_scrape_bencode($scrape);
	}
	exit;
}

////	Full Scrape Mode
if ($settings['full_scrape']) {
	require_once $settings['functions'].'function.scrape.merge.results.php';
	require_once $settings['model'].'peers.scrape.all.php';
	require_once $settings['model'].'torrents.scrape.all.php';

	$peers = peers_scrape_all($connection, $settings);
	$torrents = torrents_scrape_all($connection, $settings);

	if (!$peers || !$torrents) {
		tracker_error('Unable to scrape for that torrent.');
	}

	$scrape = scrape_merge_results($peers, $torrents);

	// Render
	if (isset($_GET['xml'])) {
		require_once $settings['views'].'xml.scrape.php';
		header('Content-Type: text/xml');
		echo view_scrape_xml($scrape);
	} elseif (isset($_GET['json'])) {
		require_once $settings['views'].'json.scrape.php';
		header('Content-Type: application/json');
		echo view_scrape_json($scrape);
	} else {
		require_once $settings['views'].'bencode.scrape.php';
		header('Content-Type: text/plain; charset=ISO-8859-1');
		echo view_scrape_bencode($scrape);
	}
	exit;
}

////	Not Allowed
// $peer['info_hash'] is always set (keyed by sanitize_tracker_params) but is
// false when the request supplied no usable hash. The request-tried-a-hash
// path produces 'Torrent is not allowed.', everything else falls through to
// 'Tracker scraping is not allowed.'.
if ($peer['info_hash']) {
	tracker_error('Torrent is not allowed.');
}
tracker_error('Tracker scraping is not allowed.');
