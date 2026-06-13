<?php

declare(strict_types=1);

////	admin_torrent_delete_action
// Handles Torrents page deletion (process=torrent_delete). Sanitizes the form
// info_hash to 40-char hex via maybe_binary_to_hex (the project's SQL-injection
// defense) and bails via tracker_error on a bad value, then deletes the torrent
// and its peer rows so the swarm disappears at once. Admin context: no owner
// guard, so it acts on any torrent. Returns a message for the panel to display.
//
// On an open tracker the torrent reappears on its next announce; deletion is
// only decisive on a closed tracker (where it is also off the allowed list).

/** @param PhoenixSettings $settings */
function admin_torrent_delete_action(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../functions/sanitize.maybe_binary_to_hex.php';
    $raw = $_POST['info_hash'] ?? '';
    $info_hash = maybe_binary_to_hex(is_string($raw) ? $raw : '');
    if ($info_hash === false) {
        tracker_error('Info Hash is invalid.');
    }

    require_once __DIR__.'/../model/torrent.delete.php';
    if (! torrent_delete($connection, $settings, $info_hash)) {
        return 'Could not delete the torrent.';
    }

    require_once __DIR__.'/../model/peers.delete.by.torrent.php';
    peers_delete_by_torrent($connection, $settings, $info_hash);

    return 'Torrent and its peers have been deleted.';
}
