<?php

require_once $settings['functions'].'function.scrape.build.where.clause.php';
require_once $settings['functions'].'function.scrape.initialize.results.php';
require_once $settings['model'].'peers.scrape.php';
require_once $settings['functions'].'function.scrape.query.torrents.php';

// BEP 15 allows a single scrape request to carry multiple info_hashes.
// Build WHERE clause and zero-initialise $scrape entries for all requested hashes
// so missing torrents still get a response rather than being silently omitted.
$where   = scrape_build_where_clause($peer['info_hashes']);
$scrape  = scrape_initialize_results($peer['info_hashes']);
$peers   = peers_scrape($connection, $settings, $where);
$torrents = scrape_query_torrents($connection, $settings, $where);

if ( !$peers || !$torrents ) {
	tracker_error('Unable to scrape for that torrent.');
}

require_once $settings['functions'].'function.scrape.output.php';
scrape_output($scrape, $peers, $torrents);
