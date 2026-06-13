<?php

declare(strict_types=1);

////	admin_torrent_listed_action
// Handles the Torrents page List/Unlist toggle (process=torrent_listed).
// Sanitizes the form info_hash to 40-char hex via maybe_binary_to_hex (the
// project's SQL-injection defense) and bails via tracker_error on a bad value,
// then sets the torrent's `listed` flag. Admin context: no owner guard, so it
// acts on any torrent. Returns a message for the panel to display.

/** @param PhoenixSettings $settings */
function admin_torrent_listed_action(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../functions/sanitize.maybe_binary_to_hex.php';
    $raw = $_POST['info_hash'] ?? '';
    $info_hash = maybe_binary_to_hex(is_string($raw) ? $raw : '');
    if ($info_hash === false) {
        tracker_error('Info Hash is invalid.');
    }

    $listed = intval($_POST['listed'] ?? 0) === 1 ? 1 : 0;

    require_once __DIR__.'/../model/torrent.set.listed.php';
    if (! torrent_set_listed($connection, $settings, $info_hash, $listed)) {
        return 'Could not update the torrent.';
    }

    return $listed === 1 ? 'Torrent is now listed.' : 'Torrent is now unlisted.';
}
