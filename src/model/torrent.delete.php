<?php

declare(strict_types=1);

////	torrent_delete
// Delete a torrent row by info_hash. When $user is non-null the DELETE is
// additionally guarded by `AND user = ?`, so a caller can only remove its own
// torrents; the API always passes the user, while the admin panel (#56) passes
// null to remove any torrent. Peer rows are not touched here — callers run
// peers_delete_by_torrent() afterwards so the swarm disappears at once.
// Returns true when the statement executed, false on error. Uses a prepared
// statement and catches the strict-mode exception so the bool contract holds
// regardless of mysqli_report() mode (mirrors torrent_add / db_connect).

/** @param PhoenixSettings $settings */
function torrent_delete(mysqli $connection, array $settings, string $info_hash, ?string $user = null): bool
{
    $sql = 'DELETE FROM `'.$settings['db_prefix'].'torrents` WHERE `info_hash` = ?';
    $params = [$info_hash];
    if ($user !== null) {
        $sql .= ' AND `user` = ?';
        $params[] = $user;
    }
    $sql .= ';';

    try {
        $result = mysqli_execute_query($connection, $sql, $params);
    } catch (mysqli_sql_exception) {
        return false;
    }

    return $result !== false;
}
