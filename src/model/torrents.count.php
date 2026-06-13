<?php

declare(strict_types=1);

////	torrents_count
// Count every registered torrent. The peer-based stats only see torrents with
// active peers, so this gives the dashboard the true total (including idle and
// unlisted torrents). Returns 0 when the table is empty or the query fails.

/** @param PhoenixSettings $settings */
function torrents_count(mysqli $connection, array $settings): int
{
    require_once __DIR__.'/db.fetch.once.php';

    $row = db_fetch_once(
        $connection,
        'SELECT COUNT(*) AS `count` FROM `'.$settings['db_prefix'].'torrents`;',
    );

    return $row === false ? 0 : intval($row['count']);
}
