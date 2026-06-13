<?php

declare(strict_types=1);

////	api/torrents.php
// Tracker management API: list every torrent (listed and unlisted, any owner)
// with swarm stats. Authenticated by an API key from $settings['api_keys'].
// Responds with JSON by default and XML when ?xml is passed.
//
// Disclosure: any valid key sees the full list — including unlisted torrents
// and torrents owned by other users. Keys are operator-issued and trusted;
// per-key scopes are a deferred follow-up.

// No Access-Control-Allow-Origin here — this is an authenticated endpoint,
// not one of the public read endpoints (announce/scrape/index).

// The API speaks JSON unless the caller asked for XML. tracker_error()
// picks its serialisation from these $_GET flags, so set the JSON flag
// before bootstrap can fail to keep even connection errors out of bencode.
if (! isset($_GET['xml'])) {
    $_GET['json'] = '1';
}

require_once __DIR__.'/../../src/phoenix.php';
require_once __DIR__.'/../../src/controller/api.torrents.php';

echo api_torrents_controller($connection, $settings);
