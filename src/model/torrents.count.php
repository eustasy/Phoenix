<?php

declare(strict_types=1);

////	torrents_count
// Count every registered torrent. The peer-based stats only see torrents with
// active peers, so this gives the dashboard the true total (including idle and
// unlisted torrents). Returns 0 when the table is empty or the query fails.
//
// Takes the same $search/$listed as torrents_select_all() and applies them
// through the same torrents_filter_sql(), so a filtered listing pages against a
// filtered total. Called with no filter (the default) it counts every torrent,
// which is what the dashboard and the sidebar badge want.
//
// No peers join: the filter only reaches torrents columns, so counting is one
// read of one table however the listing was narrowed.

/** @param PhoenixSettings $settings */
function torrents_count(mysqli $connection, array $settings, string $search = '', int $listed = -1): int
{
    require_once __DIR__.'/db.fetch.once.php';
    require_once __DIR__.'/torrents.filter.sql.php';

    $prefix = $settings['db_prefix'];
    $filter = torrents_filter_sql($search, $listed);

    if ($filter['where'] === '') {
        $row = db_fetch_once($connection, 'SELECT COUNT(*) AS `count` FROM `'.$prefix.'torrents`;');

        return $row === false ? 0 : intval($row['count']);
    }

    $result = mysqli_execute_query(
        $connection,
        'SELECT COUNT(*) AS `count` FROM `'.$prefix.'torrents` t'.$filter['where'].';',
        $filter['params'],
    );
    if (! $result instanceof mysqli_result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);

    return is_array($row) ? intval($row['count']) : 0;
}
