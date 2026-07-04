<?php

declare(strict_types=1);

////	api_index_controller
// Drives the API discovery index (/api): returns the running Phoenix version.
// Unauthenticated and ungated — no torrent data is exposed, just a version
// signature, so clients can probe the API without a key. Returns the rendered
// body — JSON by default, XML when ?xml is set.

/** @param PhoenixSettings $settings */
function api_index_controller(array $settings): string
{
    if (isset($_GET['xml'])) {
        require_once __DIR__.'/../views/xml.api.index.php';
        header('Content-Type: application/xml; charset=UTF-8');

        return view_api_index_xml($settings);
    }
    require_once __DIR__.'/../views/json.api.index.php';
    header('Content-Type: application/json');

    return view_api_index_json($settings);
}
