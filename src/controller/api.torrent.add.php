<?php

declare(strict_types=1);

////	api_torrent_add_controller
// Drives the torrent-add API: authenticate the API key → sanitize input →
// insert the torrent (owned by the key's user) → render the response body.
// Add-only: an info_hash that is already tracked is an error, so a torrent
// can never be rewritten — or taken over — through this endpoint.
// Driven by public/api/torrent/add.php; parameters come from POST or GET
// interchangeably. Returns the rendered body string — JSON by default, XML
// when ?xml is set. Calls tracker_error() on validation/auth failure (which
// exits); the entry point pre-sets the JSON flag so those errors serialise as
// JSON unless the caller asked for XML.
//
// Meta population has two layers. A multipart `.torrent` upload (field name
// `torrent`) is parsed server-side and supplies the BASE for every field —
// info_hash, name, size, filename, files, trackers, webseeds. Any explicitly
// posted parameter then OVERRIDES its parsed counterpart. With no upload, the
// core fields behave exactly as before and the meta params are optional extras.

/** @param PhoenixSettings $settings */
function api_torrent_add_controller(mysqli $connection, array $settings): string
{

    ////	Authenticate
    // Shared across every API controller: refuses when the API is off or the
    // key is invalid, otherwise returns the user the key belongs to.
    require_once __DIR__.'/../functions/api.authenticate.request.php';
    $user = api_authenticate_request($settings);

    ////	Optional .torrent upload
    // When a file is uploaded under `torrent`, parse it server-side and use its
    // output as the base for every field. We deliberately do NOT gate on
    // is_uploaded_file(): the built-in CLI server used by the smoke suite and
    // the in-process unit tests both fake $_FILES entries that the SAPI never
    // registers as genuine uploads, so the check would reject legitimate test
    // traffic. The size cap below is the real guard against abuse.
    $parsed = false;
    if (isset($_FILES['torrent']) && is_array($_FILES['torrent'])) {
        $upload = $_FILES['torrent'];

        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            tracker_error('Torrent file is invalid.');
        }

        $upload_size = isset($upload['size']) && is_int($upload['size']) ? $upload['size'] : 0;
        if ($upload_size <= 0) {
            tracker_error('Torrent file is invalid.');
        }
        if ($upload_size > $settings['torrent_upload_max']) {
            tracker_error('Torrent file is too large.');
        }

        $tmp_name = isset($upload['tmp_name']) && is_string($upload['tmp_name']) ? $upload['tmp_name'] : '';
        $raw = $tmp_name !== '' ? @file_get_contents($tmp_name) : false;
        if ($raw === false) {
            tracker_error('Torrent file is invalid.');
        }

        require_once __DIR__.'/../functions/torrent.parse.php';
        $parsed = torrent_parse($raw);
        if ($parsed === false) {
            tracker_error('Torrent file is invalid.');
        }
    }

    ////	Sanitize & Validate Input
    // info_hash: required, normalized to 40 hex chars. API callers send the
    // hex form — raw 20-byte binary survives only via maybe_binary_to_hex's
    // best effort, since $_GET/$_POST values are already urldecoded once.
    // An upload supplies the base info_hash (already 40-char hex); an explicit
    // info_hash param overrides it.
    require_once __DIR__.'/../functions/sanitize.maybe_binary_to_hex.php';
    $raw_hash = $_POST['info_hash'] ?? $_GET['info_hash'] ?? ($parsed === false ? '' : $parsed['info_hash']);
    $info_hash = maybe_binary_to_hex(is_string($raw_hash) ? $raw_hash : '');
    if ($info_hash === false) {
        tracker_error('Info Hash is invalid.');
    }

    // name: optional, trimmed to the varchar(255) column. Parsed name is the
    // base; an explicit name param overrides it.
    $base_name = $parsed === false ? null : $parsed['name'];
    $raw_name = $_POST['name'] ?? $_GET['name'] ?? $base_name;
    $name = is_string($raw_name) && $raw_name !== '' ? substr($raw_name, 0, 255) : null;

    // size: optional byte count, never negative. Parsed size is the base; an
    // explicit size param overrides it.
    $base_size = $parsed === false ? 0 : $parsed['size'];
    $raw_size = $_POST['size'] ?? $_GET['size'] ?? $base_size;
    $size = max(0, intval(is_scalar($raw_size) ? $raw_size : 0));

    // listed: whether the torrent appears on the public index. Defaults on —
    // putting the torrent on the list is the point of this endpoint.
    $raw_listed = $_POST['listed'] ?? $_GET['listed'] ?? 1;
    $listed = intval(is_scalar($raw_listed) ? $raw_listed : 1) === 0 ? 0 : 1;

    ////	Meta fields
    // Each field takes its parsed value as the base, overridden by an explicit
    // param. sanitize_torrent_meta accepts both the parsed shape (decoded list)
    // and the request shape (JSON / newline string) for each field, and yields
    // matching normalized (for views) and storage (for the DB) forms.
    require_once __DIR__.'/../functions/sanitize.torrent.meta.php';
    $meta = sanitize_torrent_meta([
        'filename' => $_POST['filename'] ?? $_GET['filename'] ?? ($parsed === false ? null : $parsed['filename']),
        'files' => $_POST['files'] ?? $_GET['files'] ?? ($parsed === false ? null : $parsed['files']),
        'trackers' => $_POST['trackers'] ?? $_GET['trackers'] ?? ($parsed === false ? null : $parsed['trackers']),
        'webseeds' => $_POST['webseeds'] ?? $_GET['webseeds'] ?? ($parsed === false ? null : $parsed['webseeds']),
    ]);

    ////	Add Torrent
    // torrent_add() takes the storage forms; the views take the normalized forms.
    $torrent = [
        'user' => $user,
        'info_hash' => $info_hash,
        'name' => $name,
        'size' => $size,
        'listed' => $listed,
        'filename' => $meta['filename']['storage'],
        'files' => $meta['files']['storage'],
        'trackers' => $meta['trackers']['storage'],
        'webseeds' => $meta['webseeds']['storage'],
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
    // Views receive the normalized meta forms, never the storage strings.
    $view = [
        'user' => $user,
        'info_hash' => $info_hash,
        'name' => $name,
        'size' => $size,
        'listed' => $listed,
        'filename' => $meta['filename']['normalized'],
        'files' => $meta['files']['normalized'],
        'trackers' => $meta['trackers']['normalized'],
        'webseeds' => $meta['webseeds']['normalized'],
    ];

    if (isset($_GET['xml'])) {
        require_once __DIR__.'/../views/xml.torrent.php';
        header('Content-Type: text/xml');

        return view_torrent_xml($view);
    }
    require_once __DIR__.'/../views/json.torrent.php';
    header('Content-Type: application/json');

    return view_torrent_json($view);
}
