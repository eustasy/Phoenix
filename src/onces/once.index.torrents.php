<?php

// Only includes torrents with listed=1; unlisted torrents are invisible to the public index.
require_once $settings['model'].'torrents.select.listed.php';
$index = torrents_select_listed($connection, $settings);

// XML
if ( isset($_GET['xml']) ) {
	require_once $settings['views'].'xml.index.php';
	header('Content-Type: text/xml');
	echo view_index_xml($index);

// JSON
} else if ( isset($_GET['json']) ) {
	require_once $settings['functions'].'function.index.render.json.php';
	header('Content-Type: application/json');
	echo index_render_json($index);

// HTML
} else {
	require_once $settings['views'].'html.index.php';
	header('Content-Type: text/html; charset=UTF-8');
	echo view_index_html($index);
}
