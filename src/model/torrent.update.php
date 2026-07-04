<?php

declare(strict_types=1);

////	torrent_update
// UPDATE an existing torrent's editable columns by info_hash: name, size,
// listed, and the four meta columns (filename, files, trackers, webseeds,
// stored verbatim from the controller's storage-ready strings). The identity
// columns are never touched — info_hash, user, and the downloads counter all
// stay as they are. When $user is non-null the UPDATE is additionally guarded
// by `AND user = ?`, so a caller can only edit its own torrents; the API passes
// the key's user (null for the '*' admin), and the admin panel passes null to
// edit any torrent. Mirrors torrent_set_listed's prepared-statement +
// strict-mode-exception handling so the bool contract holds in any
// mysqli_report() mode. Returns true when the statement executed, false on
// error. (A zero-row update still returns true — callers authorize existence
// first, as torrent_set_listed's do.)

/**
 * @param PhoenixSettings $settings
 * @param array{
 *     info_hash: string,
 *     name: string|null,
 *     size: int,
 *     listed: int,
 *     filename: string|null,
 *     files: string|null,
 *     trackers: string|null,
 *     webseeds: string|null,
 * } $torrent
 */
function torrent_update(mysqli $connection, array $settings, array $torrent, ?string $user = null): bool
{
    $sql = 'UPDATE `'.$settings['db_prefix'].'torrents` SET '.
        '`name` = ?, `size` = ?, `listed` = ?, '.
        '`filename` = ?, `files` = ?, `trackers` = ?, `webseeds` = ? '.
        'WHERE `info_hash` = ?';
    $params = [
        $torrent['name'],
        $torrent['size'],
        $torrent['listed'],
        $torrent['filename'],
        $torrent['files'],
        $torrent['trackers'],
        $torrent['webseeds'],
        $torrent['info_hash'],
    ];
    if ($user !== null) {
        $sql .= ' AND `user` = ?';
        $params[] = $user;
    }
    $sql .= ';';

    try {
        $result = mysqli_execute_query($connection, $sql, $params);
    } catch (mysqli_sql_exception $e) {
        if ($settings['report_errors']) {
            require_once __DIR__.'/../functions/phoenix.hook.event.php';
            phoenix_hook_event('error', ['throwable' => $e, 'source' => 'torrent_update']);
        }

        return false;
    }

    return $result !== false;
}
