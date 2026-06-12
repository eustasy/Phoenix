<?php

declare(strict_types=1);

////	api.php
// Tracker management API, routed by `action` (POST or GET), authenticated
// by an API key from $settings['api_keys']. Responds with JSON by default
// and XML when ?xml is passed.
//
// Actions:
//   add — add a torrent (api_torrent_add_controller)

// No Access-Control-Allow-Origin here — this is an authenticated write
// endpoint, not one of the public read endpoints (announce/scrape/index).

// The API speaks JSON unless the caller asked for XML. tracker_error()
// picks its serialisation from these $_GET flags, so set the JSON flag
// before bootstrap can fail to keep even connection errors out of bencode.
if (! isset($_GET['xml'])) {
    $_GET['json'] = '1';
}

require_once __DIR__.'/../src/phoenix.php';

////	Route by action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add') {
    require_once __DIR__.'/../src/controller/api.torrent.add.php';
    echo api_torrent_add_controller($connection, $settings);
    exit;
}

////	Not an action
// Reached when the action is missing or unrecognised. Deliberately the
// same error whether or not the request carried a valid key.
tracker_error('API action is invalid.');
