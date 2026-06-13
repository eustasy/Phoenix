<?php

declare(strict_types=1);

////	api_torrents_controller
// Drives the torrent-list API: authenticate the API key → fetch every torrent
// (listed and unlisted, any owner) with swarm stats → render the collection.
// The full list is intentional: API keys are operator-issued, so any valid
// key is trusted with unlisted torrents and other users' rows alike (per-key
// scopes are a deferred follow-up).
// Driven by public/api/torrents.php; returns the rendered body string — JSON
// by default, XML when ?xml is set. Calls tracker_error() on auth failure
// (which exits); the entry point pre-sets the JSON flag so those errors
// serialise as JSON unless the caller asked for XML.

/** @param PhoenixSettings $settings */
function api_torrents_controller(mysqli $connection, array $settings): string
{

    ////	Authenticate
    // Gate on a valid key, but ignore which user it is — this endpoint returns
    // every torrent regardless of owner.
    require_once __DIR__.'/../functions/api.authenticate.request.php';
    api_authenticate_request($settings);

    ////	Fetch
    require_once __DIR__.'/../model/torrents.select.all.php';
    $torrents = torrents_select_all($connection, $settings);

    ////	Render
    if (isset($_GET['xml'])) {
        require_once __DIR__.'/../views/xml.torrents.php';
        header('Content-Type: text/xml');

        return view_torrents_xml($torrents);
    }
    require_once __DIR__.'/../views/json.torrents.php';
    header('Content-Type: application/json');

    return view_torrents_json($torrents);
}
