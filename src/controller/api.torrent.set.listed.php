<?php

declare(strict_types=1);

////	api_torrent_set_listed_controller
// Drives the list / delist API: authenticate → sanitize info_hash → fetch the
// torrent and authorize the caller → flip its `listed` flag → render the row.
// Shared by both entry points, which pass $listed = 1 (list) or 0 (delist).
//
// Authorization (api_authenticate_mutation + api_torrent_authorize): a normal
// key may only touch its own torrents; the '*' admin (key or admin.php session)
// may touch any, including announce-created rows with no owner. A missing row
// and a not-owned row both report 'Torrent not found.', so ownership never
// discloses existence. Idempotent: re-setting the current value still succeeds.
//
// Driven by public/api/torrent/{list,delist}.php; POST only, authenticated by
// an `Authorization: Bearer <key>` header (or an admin.php session + CSRF).
// Returns the rendered body — JSON by default, XML when ?xml is set. Calls
// tracker_error() on failure (which exits); the entry point pre-sets the JSON
// flag so those errors serialise as JSON unless the caller asked for XML.

/** @param PhoenixSettings $settings */
function api_torrent_set_listed_controller(mysqli $connection, array $settings, int $listed): string
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

    ////	Mutate
    // The admin ('*') passes a null owner guard so it can flip any torrent;
    // a normal user re-asserts its ownership in the UPDATE (race-safe).
    require_once __DIR__.'/../functions/api.user.is_admin.php';
    $guard = api_user_is_admin($user) ? null : $user;

    require_once __DIR__.'/../model/torrent.set.listed.php';
    if (! torrent_set_listed($connection, $settings, $info_hash, $listed, $guard)) {
        tracker_error('Unable to update torrent.');
    }
    $torrent['listed'] = $listed;

    ////	Render
    // Build the view shape explicitly (drops the row's `downloads`, which the
    // single-torrent view does not carry).
    $view = [
        'user' => $torrent['user'],
        'info_hash' => $torrent['info_hash'],
        'name' => $torrent['name'],
        'size' => $torrent['size'],
        'listed' => $torrent['listed'],
        'filename' => $torrent['filename'],
        'files' => $torrent['files'],
        'trackers' => $torrent['trackers'],
        'webseeds' => $torrent['webseeds'],
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
