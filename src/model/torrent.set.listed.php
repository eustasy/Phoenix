<?php

declare(strict_types=1);

////	torrent_set_listed
// Set a torrent's public-index visibility (`listed` = 1 or 0) by info_hash.
// When $user is non-null the UPDATE is additionally guarded by `AND user = ?`,
// so a caller can only flip its own torrents; the API always passes the user,
// while the admin panel (#56) passes null to act on any torrent. Idempotent:
// re-setting the current value still succeeds.
// Returns true when the statement executed, false on error. Uses a prepared
// statement and catches the strict-mode exception so the bool contract holds
// regardless of mysqli_report() mode (mirrors torrent_add / db_connect).

/** @param PhoenixSettings $settings */
function torrent_set_listed(mysqli $connection, array $settings, string $info_hash, int $listed, ?string $user = null): bool
{
    $sql = 'UPDATE `'.$settings['db_prefix'].'torrents` SET `listed` = ? WHERE `info_hash` = ?';
    $params = [$listed, $info_hash];
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
