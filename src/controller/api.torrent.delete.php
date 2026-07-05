<?php

declare(strict_types=1);

////	api_torrent_delete_controller
// Drives the delete API: authenticate → enforce the deletion gate → sanitize
// info_hash → fetch the torrent and authorize the caller → delete the row and
// its peers → render the row as it was removed.
//
// Authorization mirrors list/delist (api_authenticate_mutation +
// api_torrent_authorize): a normal key may only delete its own torrents; the
// '*' admin (key or admin.php session) may delete any, including announce-
// created rows with no owner. Missing and not-owned both report 'Torrent not
// found.' so ownership never discloses existence.
//
// Gate: deletion is off by default ($settings['api_allow_delete']); a normal
// key is refused with 'Torrent deletion is disabled.' until an operator opts
// in, but the admin is always exempt. The gate runs before any torrent lookup,
// so a disabled tracker discloses nothing about which torrents exist.
//
// CAVEAT: on an OPEN tracker a deleted torrent reappears on its next announce
// (the announce auto-creates it again). Deletion is only decisive on a CLOSED
// tracker, where the torrent is also absent from the allowed list. Removing
// peers here just makes the swarm vanish immediately rather than expiring.
//
// Driven by public/api/torrent/delete.php; POST only, authenticated by an
// `Authorization: Bearer <key>` header (or an admin.php session + CSRF).
// Returns the rendered body — JSON by default, XML when ?xml is set. Calls
// tracker_error() on failure (which exits); the entry point pre-sets the JSON
// flag so those errors serialise as JSON unless the caller asked for XML.

/** @param PhoenixSettings $settings */
function api_torrent_delete_controller(mysqli $connection, array $settings): string
{
    ////	Method
    // Write endpoint: POST only.
    require_once __DIR__.'/../functions/api.require.method.php';
    api_require_method('POST');

    ////	Authenticate
    require_once __DIR__.'/../functions/api.authenticate.mutation.php';
    $user = api_authenticate_mutation($settings);

    require_once __DIR__.'/../functions/api.user.is_admin.php';
    $is_admin = api_user_is_admin($user);

    ////	Deletion gate
    // Off by default; the admin is exempt. Checked before any lookup so a
    // disabled tracker reveals nothing about which torrents exist.
    if (! $is_admin && empty($settings['api_allow_delete'])) {
        tracker_error('Torrent deletion is disabled.');
    }

    ////	Sanitize info_hash
    // 40-char hex, per the project's SQL-injection defense, before any query.
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

    ////	Delete
    // The admin ('*') passes a null owner guard so it can delete any torrent;
    // a normal user re-asserts its ownership in the DELETE (race-safe). The
    // peer rows go too, so the swarm disappears at once.
    $guard = $is_admin ? null : $user;

    require_once __DIR__.'/../model/torrent.delete.php';
    if (! torrent_delete($connection, $settings, $info_hash, $guard)) {
        tracker_error('Unable to delete torrent.');
    }

    require_once __DIR__.'/../model/peers.delete.by.torrent.php';
    peers_delete_by_torrent($connection, $settings, $info_hash);

    ////	Render the deleted row (as it was)
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
