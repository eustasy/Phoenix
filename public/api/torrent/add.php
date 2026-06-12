<?php

declare(strict_types=1);

////	api/torrent/add.php
// Tracker management API: add a torrent. Authenticated by an API key from
// $settings['api_keys']; parameters come from POST or GET. Responds with JSON
// by default and XML when ?xml is passed.

// No Access-Control-Allow-Origin here — this is an authenticated write
// endpoint, not one of the public read endpoints (announce/scrape/index).

// The API speaks JSON unless the caller asked for XML. tracker_error()
// picks its serialisation from these $_GET flags, so set the JSON flag
// before bootstrap can fail to keep even connection errors out of bencode.
if (! isset($_GET['xml'])) {
    $_GET['json'] = '1';
}

require_once __DIR__.'/../../../src/phoenix.php';
require_once __DIR__.'/../../../src/controller/api.torrent.add.php';

echo api_torrent_add_controller($connection, $settings);
