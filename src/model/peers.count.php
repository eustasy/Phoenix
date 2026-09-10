<?php

declare(strict_types=1);

////	peers_count
// Count active peer rows across every swarm — the figure behind the Peers
// sidebar badge, and the total the Peers listing pages against. A peer
// announcing to several torrents counts once per swarm (it is a distinct peers
// row each time), matching the seeders+leechers totals shown elsewhere.
//
// Takes the same $search/$state as peers_select_all() and applies them through
// the same peers_filter_sql(), so a filtered listing pages against a filtered
// total. Called with no filter (the default) it counts every peer, which is what
// the sidebar badge wants. Returns 0 when nothing matches or the query fails.

/** @param PhoenixSettings $settings */
function peers_count(mysqli $connection, array $settings, string $search = '', int $state = -1): int
{
    require_once __DIR__.'/db.fetch.once.php';
    require_once __DIR__.'/peers.filter.sql.php';

    $prefix = $settings['db_prefix'];
    $filter = peers_filter_sql($search, $state);

    // The unfiltered count needs no join; the filter can reach the torrent name,
    // so join only when there is something to filter on.
    if ($filter['where'] === '') {
        $row = db_fetch_once($connection, 'SELECT COUNT(*) AS `count` FROM `'.$prefix.'peers` p;');

        return $row === false ? 0 : intval($row['count']);
    }

    $result = mysqli_execute_query(
        $connection,
        'SELECT COUNT(*) AS `count` FROM `'.$prefix.'peers` p '.
        'LEFT JOIN `'.$prefix.'torrents` t ON t.`info_hash` = p.`info_hash`'.
        $filter['where'].';',
        $filter['params'],
    );
    if (! $result instanceof mysqli_result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);

    return is_array($row) ? intval($row['count']) : 0;
}
