<?php

declare(strict_types=1);

////	Public Torrent Index

// Allow cross-origin reads — browser-based tools may read the public index. Sent
// before bootstrap so error responses carry it too. Scoped to the public read
// endpoints (announce/scrape/index); the admin panel deliberately omits it.
header('Access-Control-Allow-Origin: *');

require_once __DIR__.'/../src/phoenix.php';

if (! $settings['public_index']) {
    tracker_error('Index is not public.');
}

////	Query listed torrents
require_once __DIR__.'/../src/model/torrents.select.listed.php';
$index = torrents_select_listed($connection, $settings);

////	Render
if (isset($_GET['xml'])) {
    require_once __DIR__.'/../src/views/xml.index.php';
    header('Content-Type: text/xml');
    echo view_index_xml($index);
} elseif (isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode($index);
} else {
    require_once __DIR__.'/../src/views/html.index.php';
    header('Content-Type: text/html; charset=UTF-8');
    echo view_index_html($index);
}
