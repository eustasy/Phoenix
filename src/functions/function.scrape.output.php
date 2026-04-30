<?php
////\tscrape_output
// Render scrape results in XML, JSON, or bencoded format based on query params.
// Accepts $scrape array and outputs directly.

require_once $settings['functions'].'function.scrape.merge.results.php';

function scrape_output($scrape, $peers = null, $torrents = null) {
	if ($peers && $torrents) {
		$scrape = scrape_merge_results($peers, $torrents, $scrape ?? array());
	}
	if (isset($_GET['xml'])) {
		require_once $GLOBALS['settings']['functions'].'function.scrape.render.xml.php';
		header('Content-Type: text/xml');
		echo scrape_render_xml($scrape);
	} elseif (isset($_GET['json'])) {
		require_once $GLOBALS['settings']['functions'].'function.scrape.render.json.php';
		header('Content-Type: application/json');
		echo scrape_render_json($scrape);
	} else {
		require_once $GLOBALS['settings']['functions'].'function.scrape.render.bencode.php';
		echo scrape_render_bencode($scrape);
	}
}
