<?php

declare(strict_types=1);

////	api/torrent/delist.php
// Tracker management API: de-list a torrent (set listed=0) so it no longer
// appears on the public index. Authorization: an owner API key (its own
// torrents) or the '*' admin — via the admin key, or an admin.php session
// carrying a CSRF token. Parameters from POST or GET. Responds with JSON by
// default and XML with ?xml.

// No Access-Control-Allow-Origin here — this is an authenticated write
// endpoint, not one of the public read endpoints (announce/scrape/index).

// The API speaks JSON unless the caller asked for XML. tracker_error()
// picks its serialisation from these $_GET flags, so set the JSON flag
// before bootstrap can fail to keep even connection errors out of bencode.
if (! isset($_GET['xml'])) {
    $_GET['json'] = '1';
}

require_once __DIR__.'/../../../src/phoenix.php';
require_once __DIR__.'/../../../src/controller/api.torrent.set.listed.php';

echo api_torrent_set_listed_controller($connection, $settings, 0);
