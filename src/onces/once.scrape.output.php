<?php

declare(strict_types=1);

require_once $settings['functions'].'function.scrape.merge.results.php';
$scrape = scrape_merge_results($peers, $torrents, $scrape ?? array());

if ( isset($_GET['xml']) ) {
	require_once $settings['functions'].'function.scrape.render.xml.php';
	header('Content-Type: text/xml');
	echo scrape_render_xml($scrape);
} else if ( isset($_GET['json']) ) {
	require_once $settings['functions'].'function.scrape.render.json.php';
	header('Content-Type: application/json');
	echo scrape_render_json($scrape);
} else {
	require_once $settings['functions'].'function.scrape.render.bencode.php';
	echo scrape_render_bencode($scrape);
}
