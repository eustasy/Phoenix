<?php

declare(strict_types=1);

////	admin_torrent_add_action
// Handles the admin panel's "Add a Torrent" action (process=torrent_add):
// sanitize the submitted fields → insert the torrent → return a message string
// for the panel to render. Mirrors api_torrent_add_controller()'s flow, but is
// driven by an authenticated admin session (CSRF-gated by the dashboard) rather
// than an API key, so it records NULL for the owner: admin-added torrents have
// no API owner, and per #64/#65 NULL-owner rows are managed by the panel and the
// '*' API admin, never by a normal API key.
//
// Unlike the API controller, every failure RETURNS its message rather than
// calling tracker_error() (which would exit) — the dashboard re-renders with the
// returned string so the admin can simply correct and retry.
//
// Meta population has two layers, identical to the API. A multipart `.torrent`
// upload (field name `torrent`) is parsed server-side and supplies the BASE for
// every field; any explicitly posted parameter then OVERRIDES its parsed
// counterpart. With no upload, the meta params are optional extras.

/** @param PhoenixSettings $settings */
function admin_torrent_add_action(mysqli $connection, array $settings): string
{

    ////	Optional .torrent upload
    // When a file is uploaded under `torrent`, parse it server-side and use its
    // output as the base for every field. We deliberately do NOT gate on
    // is_uploaded_file(): the in-process unit tests fake the $_FILES entry, which
    // the SAPI never registers as a genuine upload; the size cap is the real
    // guard against abuse.
    $parsed = false;
    if (isset($_FILES['torrent']) && is_array($_FILES['torrent'])) {
        $upload = $_FILES['torrent'];

        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Torrent file is invalid.';
        }

        $upload_size = isset($upload['size']) && is_int($upload['size']) ? $upload['size'] : 0;
        if ($upload_size <= 0) {
            return 'Torrent file is invalid.';
        }
        if ($upload_size > $settings['torrent_upload_max']) {
            return 'Torrent file is too large.';
        }

        $tmp_name = isset($upload['tmp_name']) && is_string($upload['tmp_name']) ? $upload['tmp_name'] : '';
        $raw = $tmp_name !== '' ? @file_get_contents($tmp_name) : false;
        if ($raw === false) {
            return 'Torrent file is invalid.';
        }

        require_once __DIR__.'/../functions/torrent.parse.php';
        $parsed = torrent_parse($raw);
        if ($parsed === false) {
            return 'Torrent file is invalid.';
        }
    }

    ////	Sanitize & Validate Input
    // info_hash: required, normalized to 40 hex chars via maybe_binary_to_hex —
    // the project's SQL-injection defense. An upload supplies the base info_hash
    // (already 40-char hex); an explicit info_hash param overrides it.
    require_once __DIR__.'/../functions/sanitize.maybe_binary_to_hex.php';
    $raw_hash = $_POST['info_hash'] ?? ($parsed === false ? '' : $parsed['info_hash']);
    $info_hash = maybe_binary_to_hex(is_string($raw_hash) ? $raw_hash : '');
    if ($info_hash === false) {
        return 'Info Hash is invalid.';
    }

    // name: optional, trimmed to the varchar(255) column. Parsed name is the
    // base; an explicit name param overrides it.
    $base_name = $parsed === false ? null : $parsed['name'];
    $raw_name = $_POST['name'] ?? $base_name;
    $name = is_string($raw_name) && $raw_name !== '' ? substr($raw_name, 0, 255) : null;

    // size: optional byte count, never negative. Parsed size is the base; an
    // explicit size param overrides it.
    $base_size = $parsed === false ? 0 : $parsed['size'];
    $raw_size = $_POST['size'] ?? $base_size;
    $size = max(0, intval(is_scalar($raw_size) ? $raw_size : 0));

    // listed: whether the torrent appears on the public index. The form ships a
    // checkbox checked by default; an unchecked box sends nothing, so an absent
    // value here means delisted.
    $raw_listed = $_POST['listed'] ?? 0;
    $listed = intval(is_scalar($raw_listed) ? $raw_listed : 0) === 0 ? 0 : 1;

    ////	Meta fields
    // Each field takes its parsed value as the base, overridden by an explicit
    // param. sanitize_torrent_meta accepts both the parsed shape (decoded list)
    // and the request shape (JSON / newline string) for each field, and yields
    // matching normalized (for views) and storage (for the DB) forms.
    require_once __DIR__.'/../functions/sanitize.torrent.meta.php';
    $meta = sanitize_torrent_meta([
        'filename' => $_POST['filename'] ?? ($parsed === false ? null : $parsed['filename']),
        'files' => $_POST['files'] ?? ($parsed === false ? null : $parsed['files']),
        'trackers' => $_POST['trackers'] ?? ($parsed === false ? null : $parsed['trackers']),
        'webseeds' => $_POST['webseeds'] ?? ($parsed === false ? null : $parsed['webseeds']),
    ]);

    ////	Add Torrent
    // torrent_add() takes the storage forms. The owner is recorded as NULL: an
    // admin-added torrent has no API owner.
    $torrent = [
        'user' => null,
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
        return 'Torrent already exists.';
    }
    if ($added !== true) {
        return 'Unable to add torrent.';
    }

    return 'Torrent added.';
}
