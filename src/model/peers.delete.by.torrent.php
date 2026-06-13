<?php

declare(strict_types=1);

////	peers_delete_by_torrent
// DELETE every peer row for one torrent (by info_hash). Run right after a
// successful torrent_delete() so the swarm disappears immediately rather than
// expiring on the next peers_clean() pass. Pattern mirrors peers_clean().
// Returns true when the statement executed, false on error. Uses a prepared
// statement and catches the strict-mode exception so the bool contract holds
// regardless of mysqli_report() mode.

/** @param PhoenixSettings $settings */
function peers_delete_by_torrent(mysqli $connection, array $settings, string $info_hash): bool
{
    try {
        $result = mysqli_execute_query(
            $connection,
            'DELETE FROM `'.$settings['db_prefix'].'peers` WHERE `info_hash` = ?;',
            [$info_hash],
        );
    } catch (mysqli_sql_exception) {
        return false;
    }

    return $result !== false;
}
