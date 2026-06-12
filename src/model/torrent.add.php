<?php

declare(strict_types=1);

////	torrent_add
// INSERT a torrent row from the API. Add-only: an info_hash that is already
// tracked (added earlier, or auto-created by an announce) is refused, never
// updated. Returns true on insert, 'exists' on a duplicate info_hash, or
// false on any other failure.

/**
 * @param PhoenixSettings $settings
 * @param array{user: string, info_hash: string, name: string|null, size: int, listed: int} $torrent
 * @return true|'exists'|false
 */
function torrent_add(mysqli $connection, array $settings, array $torrent): bool|string
{
    // A duplicate is an expected outcome, not an exceptional one: catch the
    // strict-mode exception (the PHP 8.1+ mysqli_report default) and fall
    // through to the errno check, so both reporting modes behave the same —
    // mirroring db_connect's mode-agnostic contract. The plain INSERT keeps
    // the add atomic, so a concurrent duplicate lands here too.
    try {
        $result = mysqli_execute_query(
            $connection,
            'INSERT INTO `'.$settings['db_prefix'].'torrents` '.
            '(`user`, `name`, `info_hash`, `size`, `listed`) '.
            'VALUES (?, ?, ?, ?, ?);',
            [
                $torrent['user'],
                $torrent['name'],
                $torrent['info_hash'],
                $torrent['size'],
                $torrent['listed'],
            ],
        );
    } catch (mysqli_sql_exception) {
        $result = false;
    }

    if ($result) {
        return true;
    }

    // 1062 = ER_DUP_ENTRY: the info_hash is already tracked.
    return mysqli_errno($connection) === 1062 ? 'exists' : false;
}
