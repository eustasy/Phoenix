<?php

declare(strict_types=1);

////	admin_torrent_edit_action
// Handles the admin Edit Torrent submit (process=torrent_edit): a full replace
// of the editable fields from the pre-filled form. The admin may edit any
// torrent, so there is no owner guard. info_hash, owner, and the downloads
// counter are never touched. Returns a message for the panel. The caller has
// already verified the CSRF token and validated info_hash as 40-char hex.

/** @param PhoenixSettings $settings */
function admin_torrent_edit_action(mysqli $connection, array $settings, string $info_hash): string
{
    // The torrent must exist to be edited (the form is only shown for one that
    // does, but a stale or forged submit could reference a deleted hash).
    require_once __DIR__.'/../model/torrent.select.one.php';
    if (torrent_select_one($connection, $settings, $info_hash) === false) {
        return 'Torrent not found.';
    }

    // Full replace from the submitted form. A blank field clears its value;
    // listed follows the checkbox (an unchecked box sends nothing → unlisted).
    $raw_name = $_POST['name'] ?? '';
    $name = is_string($raw_name) && trim($raw_name) !== '' ? substr($raw_name, 0, 255) : null;

    $raw_size = $_POST['size'] ?? 0;
    $size = max(0, intval(is_scalar($raw_size) ? $raw_size : 0));

    $listed = isset($_POST['listed']) ? 1 : 0;

    require_once __DIR__.'/../functions/sanitize.torrent.meta.php';
    $meta = sanitize_torrent_meta([
        'filename' => $_POST['filename'] ?? null,
        'files' => $_POST['files'] ?? null,
        'trackers' => $_POST['trackers'] ?? null,
        'webseeds' => $_POST['webseeds'] ?? null,
    ]);

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
    ], null);

    return $updated ? 'Torrent updated.' : 'Unable to update torrent.';
}
