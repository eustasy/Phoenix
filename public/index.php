<?php

declare(strict_types=1);

////	Public Torrent Index

// Allow cross-origin reads — browser-based tools may read the public index. Sent
// before bootstrap so error responses carry it too. Scoped to the public read
// endpoints (announce/scrape/index); the admin panel deliberately omits it.
header('Access-Control-Allow-Origin: *');

// Security headers up front (before bootstrap) so bootstrap errors and the
// machine-format (XML/JSON) responses carry the baseline tracker profile
// (nosniff). The HTML branch below upgrades to the public-HTML set.
require_once __DIR__.'/../src/functions/http.security.headers.php';
http_security_headers('tracker');

require_once __DIR__.'/../src/phoenix.php';

if (! $settings['public_index']) {
    tracker_error('Index is not public.');
}

////	Query listed torrents
require_once __DIR__.'/../src/model/torrents.select.listed.php';
$index = torrents_select_listed($connection, $settings);

////	Build magnet links
// Built once here so all three views emit the same URI. Stored tracker and
// webseed meta rides along only when index_show_meta allows it, so the
// magnet doesn't bypass the meta gate.
require_once __DIR__.'/../src/functions/magnet.build.php';
$show_meta = (bool) $settings['index_show_meta'];
foreach ($index as &$torrent) {
    $torrent['magnet'] = magnet_build($torrent, $settings['announce_url'], $show_meta);
}
unset($torrent);

////	Render
if (isset($_GET['xml'])) {
    require_once __DIR__.'/../src/views/xml.index.php';
    header('Content-Type: application/xml; charset=UTF-8');
    echo view_index_xml($index, $show_meta);
} elseif (isset($_GET['json'])) {
    require_once __DIR__.'/../src/views/json.index.php';
    header('Content-Type: application/json; charset=UTF-8');
    echo view_index_json($index, $show_meta);
} else {
    require_once __DIR__.'/../src/views/html.index.php';
    // Browser-facing HTML → upgrade the baseline tracker headers to the
    // public-HTML set (CSP, Referrer-Policy, SAMEORIGIN frame guard).
    http_security_headers('public_html');
    header('Content-Type: text/html; charset=UTF-8');
    echo view_index_html($index, $show_meta, $settings['phoenix_version']);
}
