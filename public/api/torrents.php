<?php

declare(strict_types=1);

////	api/torrents.php
// Tracker management API: list torrents with swarm stats. GET only,
// authenticated by an `Authorization: Bearer <key>` header (or an admin.php
// session — no CSRF needed on a read). A normal key is scoped to its OWN
// torrents; the '*' admin sees every torrent — listed and unlisted, any owner.
// Responds with JSON by default and XML when ?xml is passed.

// No Access-Control-Allow-Origin here — this is an authenticated endpoint,
// not one of the public read endpoints (announce/scrape/index).

// The API speaks JSON unless the caller asked for XML. tracker_error()
// picks its serialisation from these $_GET flags, so set the JSON flag
// before bootstrap can fail to keep even connection errors out of bencode.
if (! isset($_GET['xml'])) {
    $_GET['json'] = '1';
}

// The API serves authenticated JSON/XML data and loads no assets, so emit the
// locked-down api security headers before bootstrap — bootstrap and auth errors
// via tracker_error() then carry them too.
require_once __DIR__.'/../../src/functions/http.security.headers.php';
http_security_headers('api');

require_once __DIR__.'/../../src/phoenix.php';
require_once __DIR__.'/../../src/controller/api.torrents.php';

echo api_torrents_controller($connection, $settings);
