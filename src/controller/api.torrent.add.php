<?php

declare(strict_types=1);

////	api_torrent_add_controller
// Drives the torrent-add API: authenticate the API key → sanitize input →
// insert the torrent (owned by the key's user) → render the response body.
// Add-only: an info_hash that is already tracked is an error, so a torrent
// can never be rewritten — or taken over — through this endpoint.
// Dispatched from public/api.php for `action=add`; parameters come from
// POST or GET interchangeably. Returns the rendered body string — JSON by
// default, XML when ?xml is set. Calls tracker_error() on validation/auth
// failure (which exits); public/api.php pre-sets the JSON flag so those
// errors serialise as JSON unless the caller asked for XML.

/** @param PhoenixSettings $settings */
function api_torrent_add_controller(mysqli $connection, array $settings): string
{

    ////	Authenticate
    // No configured keys means the API is off — refuse before reading input.
    if (empty($settings['api_keys'])) {
        tracker_error('API is not enabled.');
    }

    require_once __DIR__.'/../functions/api.authenticate.key.php';
    $key = $_POST['key'] ?? $_GET['key'] ?? '';
    $user = api_authenticate_key($settings, is_string($key) ? $key : '');
    if ($user === false) {
        tracker_error('API key is invalid.');
    }

    ////	Sanitize & Validate Input
    // info_hash: required, normalized to 40 hex chars. API callers send the
    // hex form — raw 20-byte binary survives only via maybe_binary_to_hex's
    // best effort, since $_GET/$_POST values are already urldecoded once.
    require_once __DIR__.'/../functions/sanitize.maybe_binary_to_hex.php';
    $raw_hash = $_POST['info_hash'] ?? $_GET['info_hash'] ?? '';
    $info_hash = maybe_binary_to_hex(is_string($raw_hash) ? $raw_hash : '');
    if ($info_hash === false) {
        tracker_error('Info Hash is invalid.');
    }

    // name: optional, trimmed to the varchar(255) column.
    $raw_name = $_POST['name'] ?? $_GET['name'] ?? null;
    $name = is_string($raw_name) && $raw_name !== '' ? substr($raw_name, 0, 255) : null;

    // size: optional byte count, never negative.
    $raw_size = $_POST['size'] ?? $_GET['size'] ?? 0;
    $size = max(0, intval(is_scalar($raw_size) ? $raw_size : 0));

    // listed: whether the torrent appears on the public index. Defaults on —
    // putting the torrent on the list is the point of this endpoint.
    $raw_listed = $_POST['listed'] ?? $_GET['listed'] ?? 1;
    $listed = intval(is_scalar($raw_listed) ? $raw_listed : 1) === 0 ? 0 : 1;

    ////	Add Torrent
    $torrent = [
        'user' => $user,
        'info_hash' => $info_hash,
        'name' => $name,
        'size' => $size,
        'listed' => $listed,
    ];

    require_once __DIR__.'/../model/torrent.add.php';
    $added = torrent_add($connection, $settings, $torrent);
    if ($added === 'exists') {
        tracker_error('Torrent already exists.');
    }
    if ($added !== true) {
        tracker_error('Unable to add torrent.');
    }

    ////	Render
    if (isset($_GET['xml'])) {
        require_once __DIR__.'/../views/xml.torrent.add.php';
        header('Content-Type: text/xml');

        return view_torrent_add_xml($torrent);
    }
    require_once __DIR__.'/../views/json.torrent.add.php';
    header('Content-Type: application/json');

    return view_torrent_add_json($torrent);
}
