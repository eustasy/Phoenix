<?php

declare(strict_types=1);

////	torrent_user
// Looks up the owning `user` for a torrent by info_hash (prepared SELECT).
// Returns '' when the torrent is absent or its owner is NULL — the events
// ledger stores the torrent owner, not the peer, so a missing owner is just
// an empty label, never an error.

/**
 * @param PhoenixSettings $settings
 */
function torrent_user(mysqli $connection, array $settings, string $info_hash): string
{
    require_once __DIR__.'/db.fetch.once.php';

    $row = db_fetch_once(
        $connection,
        'SELECT `user` FROM `'.$settings['db_prefix'].'torrents` WHERE `info_hash`=?;',
        [$info_hash],
    );

    if ($row === false || $row['user'] === null) {
        return '';
    }

    return (string) $row['user'];
}
