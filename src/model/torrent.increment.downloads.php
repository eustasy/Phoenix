<?php

declare(strict_types=1);

////	torrent_increment_downloads
// INSERT ... ON DUPLICATE KEY UPDATE downloads counter.
// Inserts torrent row with downloads=1 if new, or increments existing counter.
// Used when a peer announces event=completed.
// Silently returns true even on failure (non-critical operation).

/** @param PhoenixSettings $settings */
function torrent_increment_downloads(mysqli $connection, array $settings, string $info_hash): true
{
    mysqli_execute_query(
        $connection,
        'INSERT INTO `'.$settings['db_prefix'].'torrents` '.
        '(`info_hash`, `downloads`) '.
        'VALUES (?, 1) '.
        'ON DUPLICATE KEY UPDATE '.
            '`downloads`=`downloads`+1;',
        [$info_hash],
    );

    // Silently fail (non-critical operation)
    return true;
}
