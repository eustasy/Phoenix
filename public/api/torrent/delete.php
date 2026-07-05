<?php

declare(strict_types=1);

////	api/torrent/delete.php
// Tracker management API: delete a torrent and its peers. POST only.
// Authorization: an `Authorization: Bearer <key>` header — an owner key (its
// own torrents) or the '*' admin (any torrent) — or an admin.php session
// carrying a CSRF token. Gated by $settings['api_allow_delete'] for non-admins
// (off by default); the admin is always exempt. Parameters come from the POST
// body. Responds with JSON by default and XML with ?xml.
//
// CAVEAT: on an OPEN tracker a deleted torrent reappears on its next announce.
// Deletion is only decisive on a CLOSED tracker, where it is also absent from
// the allowed list.

// No Access-Control-Allow-Origin here — this is an authenticated write
// endpoint, not one of the public read endpoints (announce/scrape/index).

// The API speaks JSON unless the caller asked for XML. tracker_error()
// picks its serialisation from these $_GET flags, so set the JSON flag
// before bootstrap can fail to keep even connection errors out of bencode.
if (! isset($_GET['xml'])) {
    $_GET['json'] = '1';
}

// The API serves authenticated JSON/XML data and loads no assets, so emit the
// locked-down api security headers before bootstrap — bootstrap and auth errors
// via tracker_error() then carry them too.
require_once __DIR__.'/../../../src/functions/http.security.headers.php';
http_security_headers('api');

require_once __DIR__.'/../../../src/phoenix.php';
require_once __DIR__.'/../../../src/controller/api.torrent.delete.php';

echo api_torrent_delete_controller($connection, $settings);
