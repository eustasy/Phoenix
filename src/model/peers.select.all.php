<?php

declare(strict_types=1);

////	peers_select_all
// Returns a page of peers across every swarm — the data behind the admin global
// Peers listing — swarm-wide, or narrowed to one swarm by $info_hash, which is
// what the per-torrent drill-down uses. LEFT JOINs torrents so each row carries its
// torrent name (null for an unregistered swarm). Paged by $limit/$offset, and
// filtered/ordered server-side so search and sort see every peer rather than
// only the rendered page.
//
// $limit and $offset are typed int (clamped here) and inlined, so they carry no
// SQL-injection risk. $sort is resolved through a whitelist and $dir collapses
// to one of two literals — neither is interpolated from user input. The only
// untrusted string, $search, is bound as a parameter by peers_filter_sql().
//
// Sorting is limited to stored columns: the client label and country shown in
// the table are derived per-request in PHP, so they cannot be ordered here.
//
// Returns an empty array when there are no peers in range.

/**
 * @param PhoenixSettings $settings
 * @return list<array{
 *     info_hash: string,
 *     peer_id: string,
 *     ipv4: string,
 *     ipv6: string,
 *     portv4: int,
 *     portv6: int,
 *     uploaded: int,
 *     downloaded: int,
 *     left: int,
 *     state: int,
 *     updated: int,
 *     name: string|null,
 * }>
 */
function peers_select_all(
    mysqli $connection,
    array $settings,
    int $limit,
    int $offset,
    string $search = '',
    int $state = -1,
    string $sort = 'updated',
    string $dir = 'desc',
    string $info_hash = '',
): array {
    require_once __DIR__.'/peers.filter.sql.php';

    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $prefix = $settings['db_prefix'];

    // Whitelist: the key arrives from the query string, the value never does.
    $columns = [
        'updated' => 'p.`updated`',
        'uploaded' => 'p.`uploaded`',
        'downloaded' => 'p.`downloaded`',
        'left' => 'p.`left`',
        'state' => 'p.`state`',
        'torrent' => 't.`name`',
        'address' => 'p.`ipv4`',
    ];
    $order = $columns[$sort] ?? $columns['updated'];
    $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

    $filter = peers_filter_sql($search, $state, $info_hash);

    $result = mysqli_execute_query(
        $connection,
        'SELECT p.`info_hash`, p.`peer_id`, p.`ipv4`, p.`ipv6`, p.`portv4`, p.`portv6`, '.
        'p.`uploaded`, p.`downloaded`, p.`left`, p.`state`, p.`updated`, t.`name` '.
        'FROM `'.$prefix.'peers` p '.
        'LEFT JOIN `'.$prefix.'torrents` t ON t.`info_hash` = p.`info_hash`'.
        $filter['where'].
        ' ORDER BY '.$order.' '.$direction.
        // Tie-break on the primary key so paging is stable: without it, rows
        // sharing a sort value can reappear or vanish between pages.
        ', p.`info_hash` ASC, p.`peer_id` ASC'.
        ' LIMIT '.$limit.' OFFSET '.$offset.';',
        $filter['params'],
    );
    if (! $result instanceof mysqli_result) {
        tracker_error('Unable to get peers.');
    }

    $peers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $peers[] = [
            'info_hash' => is_string($row['info_hash']) ? $row['info_hash'] : '',
            'peer_id' => is_string($row['peer_id']) ? $row['peer_id'] : '',
            'ipv4' => is_string($row['ipv4']) ? $row['ipv4'] : '',
            'ipv6' => is_string($row['ipv6']) ? $row['ipv6'] : '',
            'portv4' => intval($row['portv4']),
            'portv6' => intval($row['portv6']),
            'uploaded' => intval($row['uploaded']),
            'downloaded' => intval($row['downloaded']),
            'left' => intval($row['left']),
            'state' => intval($row['state']),
            'updated' => intval($row['updated']),
            'name' => is_string($row['name']) ? $row['name'] : null,
        ];
    }

    return $peers;
}
