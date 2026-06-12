<?php

declare(strict_types=1);

////	event_insert
// Append one row to the <prefix>events stat-tracking ledger. Prepared INSERT
// (mysqli_execute_query, like torrent_add) so the free-text `client` field
// cannot carry SQL metacharacters. Stat logging is best-effort and must never
// disrupt an announce, so a failure is swallowed silently — no tracker_error —
// and the strict-mode mysqli exception (PHP 8.1+ default) is caught the same
// way torrent_add does. Returns true on insert, false on any failure.

/**
 * @param PhoenixSettings $settings
 * @param array{
 *     time: int,
 *     info_hash: string,
 *     event: string,
 *     client: string,
 *     user: string,
 *     country: string,
 *     continent: string,
 * } $event
 */
function event_insert(mysqli $connection, array $settings, array $event): bool
{
    try {
        $result = mysqli_execute_query(
            $connection,
            'INSERT INTO `'.$settings['db_prefix'].'events` '.
            '(`time`, `info_hash`, `event`, `client`, `user`, `country`, `continent`) '.
            'VALUES (?, ?, ?, ?, ?, ?, ?);',
            [
                $event['time'],
                $event['info_hash'],
                $event['event'],
                $event['client'],
                $event['user'],
                $event['country'],
                $event['continent'],
            ],
        );
    } catch (mysqli_sql_exception) {
        return false;
    }

    return (bool) $result;
}
