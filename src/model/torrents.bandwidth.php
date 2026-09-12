<?php

declare(strict_types=1);

////	torrents_bandwidth
// Per-torrent traffic, for the Traffic page's table. Two measures, because the
// tracker holds two genuinely different numbers and neither is a substitute
// for the other:
//
//   'events' — all-time, size x downloads. Available for every torrent without
//              the events ledger, but an ESTIMATE: it assumes every completion
//              transferred the file exactly once, so it counts no partial and
//              no repeat downloads.
//
//   'peers'  — right now, the sum of the uploaded/downloaded counters the peers
//              currently in each swarm report. Real measured bytes rather than
//              an estimate, but client-reported (so trustworthy enough to rank
//              by, not to bill on), reset when a client restarts, and gone when
//              a peer leaves — this is a snapshot of the live swarm, not a
//              total.
//
// $measure selects between them; anything else falls back to 'events'. It also
// sets the default ordering, since the measure on show is the one worth ranking
// by; $sort overrides that with a whitelist key.
//
// $search, $info_hash and the paging go through torrents_filter_sql(), shared
// with torrents_count() so a filtered listing pages against a filtered total —
// the same arrangement the Torrents listing uses, and for the same reason: a
// row per torrent is unbounded work, and a browser-side filter would only ever
// search the page it was given.
//
// $limit and $offset are clamped and inlined. Returns rows ordered by that
// measure, highest first, carrying both figures so the view can show the other
// as context.

/**
 * @param PhoenixSettings $settings
 * @return list<array{info_hash: string, name: string|null, filename: string|null, user: string|null, size: int, downloads: int, estimated: int, uploaded: int, downloaded: int, peers: int}>
 */
function torrents_bandwidth(
    mysqli $connection,
    array $settings,
    string $measure = 'events',
    int $limit = 100,
    int $offset = 0,
    string $search = '',
    string $info_hash = '',
    string $sort = '',
    string $dir = 'desc',
): array {
    require_once __DIR__.'/torrents.filter.sql.php';

    $prefix = $settings['db_prefix'];
    $limit = max(1, min(500, $limit));
    $offset = max(0, $offset);

    $estimated = 'IFNULL(`t`.`size`, 0) * `t`.`downloads`';
    $uploaded = 'IFNULL(SUM(`p`.`uploaded`), 0)';

    // Whitelist: the key arrives from the query string, the value never does.
    // 'bandwidth' is whichever figure the metric is showing, so the column the
    // table leads with is always sortable under the same name.
    $columns = [
        'bandwidth' => $measure === 'peers' ? $uploaded : $estimated,
        'name' => '`t`.`name`',
        'filename' => '`t`.`filename`',
        'user' => '`t`.`user`',
        'size' => 'IFNULL(`t`.`size`, 0)',
        'downloads' => '`t`.`downloads`',
        'peers' => 'COUNT(`p`.`peer_id`)',
    ];
    $order = $columns[$sort] ?? $columns['bandwidth'];
    $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

    $filter = torrents_filter_sql($search, -1, $info_hash);

    $result = mysqli_execute_query(
        $connection,
        'SELECT `t`.`info_hash`, `t`.`name`, `t`.`filename`, `t`.`user`, IFNULL(`t`.`size`, 0) AS `size`, `t`.`downloads`, '.
        $estimated.' AS `estimated`, '.
        $uploaded.' AS `uploaded`, '.
        'IFNULL(SUM(`p`.`downloaded`), 0) AS `downloaded`, '.
        'COUNT(`p`.`peer_id`) AS `peers` '.
        'FROM `'.$prefix.'torrents` `t` '.
        'LEFT JOIN `'.$prefix.'peers` `p` ON `p`.`info_hash` = `t`.`info_hash`'.
        $filter['where'].' '.
        'GROUP BY `t`.`info_hash` '.
        'ORDER BY '.$order.' '.$direction.
        // Tie-break on the primary key so paging is stable: without it, rows
        // sharing a sort value can reappear or vanish between pages.
        ', `t`.`info_hash` ASC '.
        'LIMIT '.$limit.' OFFSET '.$offset.';',
        $filter['params'],
    );
    if (! $result instanceof mysqli_result) {
        return [];
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = [
            'info_hash' => is_string($row['info_hash']) ? $row['info_hash'] : '',
            'name' => is_string($row['name']) ? $row['name'] : null,
            'filename' => is_string($row['filename']) ? $row['filename'] : null,
            'user' => is_string($row['user']) ? $row['user'] : null,
            'size' => intval($row['size']),
            'downloads' => intval($row['downloads']),
            'estimated' => intval($row['estimated']),
            'uploaded' => intval($row['uploaded']),
            'downloaded' => intval($row['downloaded']),
            'peers' => intval($row['peers']),
        ];
    }

    return $rows;
}
