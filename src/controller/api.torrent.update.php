<?php

declare(strict_types=1);

////	api_torrent_update_controller
// Drives the torrent-update API: authenticate → sanitize info_hash → fetch the
// torrent and authorize the caller → apply the changed fields → render the row.
//
// Authorization (api_authenticate_mutation + api_torrent_authorize): a normal
// key may only edit its own torrents; the '*' admin (key or admin.php session)
// may edit any, including announce-created rows with no owner. A missing row
// and a not-owned row both report 'Torrent not found.', so ownership never
// discloses existence — same contract as list / delist / delete.
//
// Partial update: a field present in the request replaces the stored value
// (even when empty, so a field can be cleared); an absent field keeps its
// current value. info_hash, owner, and the downloads counter are immutable here.
//
// Driven by public/api/torrent/update.php; POST only, authenticated by an
// `Authorization: Bearer <key>` header (or an admin.php session + CSRF token).
// Returns the rendered body — JSON by default, XML when ?xml is set. Calls
// tracker_error() on failure (which exits); the entry point pre-sets the JSON
// flag so those errors serialise as JSON unless the caller asked for XML.

/** @param PhoenixSettings $settings */
function api_torrent_update_controller(mysqli $connection, array $settings): string
{
    ////	Method
    // Write endpoint: POST only.
    require_once __DIR__.'/../functions/api.require.method.php';
    api_require_method('POST');

    ////	Authenticate
    require_once __DIR__.'/../functions/api.authenticate.mutation.php';
    $user = api_authenticate_mutation($settings);

    ////	Sanitize info_hash
    // Normalize to 40-char hex before any query.
    require_once __DIR__.'/../functions/sanitize.maybe_binary_to_hex.php';
    $raw_hash = $_POST['info_hash'] ?? $_GET['info_hash'] ?? '';
    $info_hash = maybe_binary_to_hex(is_string($raw_hash) ? $raw_hash : '');
    if ($info_hash === false) {
        tracker_error('Info Hash is invalid.');
    }

    ////	Fetch & authorize
    require_once __DIR__.'/../model/torrent.select.one.php';
    $torrent = torrent_select_one($connection, $settings, $info_hash);

    require_once __DIR__.'/../functions/api.torrent.authorize.php';
    if ($torrent === false || ! api_torrent_authorize($user, $torrent['user'])) {
        tracker_error('Torrent not found.');
    }

    ////	Resolve the changed fields
    // A field the caller provided (POST then GET, even if empty) replaces the
    // stored value; anything absent keeps its current value. The current meta is
    // already normalized, which sanitize_torrent_meta accepts just like the
    // parsed-torrent shape, so unchanged fields round-trip cleanly.
    $merge = static function (string $key, mixed $current): mixed {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }

        return $current;
    };

    $raw_name = $merge('name', $torrent['name']);
    $name = is_string($raw_name) && trim($raw_name) !== '' ? substr($raw_name, 0, 255) : null;

    $raw_size = $merge('size', $torrent['size']);
    $size = max(0, intval(is_scalar($raw_size) ? $raw_size : 0));

    $raw_listed = $merge('listed', $torrent['listed']);
    $listed = intval(is_scalar($raw_listed) ? $raw_listed : 0) === 0 ? 0 : 1;

    require_once __DIR__.'/../functions/sanitize.torrent.meta.php';
    $meta = sanitize_torrent_meta([
        'filename' => $merge('filename', $torrent['filename']),
        'files' => $merge('files', $torrent['files']),
        'trackers' => $merge('trackers', $torrent['trackers']),
        'webseeds' => $merge('webseeds', $torrent['webseeds']),
    ]);

    ////	Update
    // The admin ('*') passes a null owner guard so it can edit any torrent; a
    // normal user re-asserts its ownership in the UPDATE (race-safe).
    require_once __DIR__.'/../functions/api.user.is_admin.php';
    $guard = api_user_is_admin($user) ? null : $user;

    require_once __DIR__.'/../model/torrent.update.php';
    $updated = torrent_update($connection, $settings, [
        'info_hash' => $info_hash,
        'name' => $name,
        'size' => $size,
        'listed' => $listed,
        'filename' => $meta['filename']['storage'],
        'files' => $meta['files']['storage'],
        'trackers' => $meta['trackers']['storage'],
        'webseeds' => $meta['webseeds']['storage'],
    ], $guard);
    if (! $updated) {
        tracker_error('Unable to update torrent.');
    }

    ////	Render
    // Views receive the normalized meta forms, never the storage strings. Owner
    // is unchanged, so echo the row's existing value.
    $view = [
        'user' => $torrent['user'],
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
        header('Content-Type: application/xml; charset=UTF-8');

        return view_torrent_xml($view);
    }
    require_once __DIR__.'/../views/json.torrent.php';
    header('Content-Type: application/json; charset=UTF-8');

    return view_torrent_json($view);
}
