<?php

declare(strict_types=1);

////	api/index.php
// API discovery index (/api): returns the running Phoenix version.
// Unauthenticated and ungated — no torrent data is exposed, just a version
// signature, so clients can probe the API surface without a key. Responds with
// JSON by default and XML when ?xml is passed.

// No Access-Control-Allow-Origin here — consistent with the other API
// endpoints (not one of the public read endpoints announce/scrape/index).

// The API speaks JSON unless the caller asked for XML. tracker_error()
// picks its serialisation from these $_GET flags, so set the JSON flag
// before bootstrap can fail to keep even connection errors out of bencode.
if (! isset($_GET['xml'])) {
    $_GET['json'] = '1';
}

// The API serves JSON/XML data and loads no assets, so emit the locked-down api
// security headers before bootstrap — bootstrap errors via tracker_error() then
// carry them too.
require_once __DIR__.'/../../src/functions/http.security.headers.php';
http_security_headers('api');

require_once __DIR__.'/../../src/phoenix.php';
require_once __DIR__.'/../../src/controller/api.index.php';

echo api_index_controller($settings);
