<?php

declare(strict_types=1);

////	torrents_top
// The dashboard's torrent mini-tables: the top few torrents by one measure.
// One query shape serves all of them, because they differ only in what they
// order by and which rows they keep.
//
// $measure is one of:
//   'seeders'  — most seeded
//   'leechers' — most leeched
//   'bandwidth'  — most bytes served, all-time (size x downloads, an estimate:
//                it assumes every completion transferred the file exactly once,
//                so it counts no partial and no repeat downloads)
//   'trouble'  — leechers waiting on too few seeders, worst first. Uses the same
//                threshold as the public index's health bar (red, under 25%
//                seeder share), so the dashboard and the index agree on what
//                "unhealthy" means, and only counts swarms that have someone
//                waiting — a torrent nobody wants is idle, not in trouble.
//
// $limit is clamped and inlined as an int; $measure only ever selects a literal
// from the map below, so no untrusted string reaches the query.
//
// Returns a list of ['info_hash', 'name', 'filename', 'seeders', 'leechers',
// 'downloads', 'bandwidth'], highest first, empty when nothing qualifies.
//
// The filename rides along unrendered: a card has room for one line, but two
// torrents can share a display name (the same release rebuilt), and the hash
// and filename are what tell them apart on hover.

/**
 * @param PhoenixSettings $settings
 * @return list<array{info_hash: string, name: string|null, filename: string|null, seeders: int, leechers: int, downloads: int, traffic: int}>
 */
function torrents_top(mysqli $connection, array $settings, string $measure = 'seeders', int $limit = 5): array
{
    $prefix = $settings['db_prefix'];
    $limit = max(1, min(50, $limit));

    $seeders = 'IFNULL(SUM(`p`.`state` = \'1\'), 0)';
    $leechers = 'IFNULL(SUM(`p`.`state` = \'0\'), 0)';
    $bandwidth = '`t`.`size` * `t`.`downloads`';

    // Per measure: ORDER BY, a row-level WHERE, and an aggregate HAVING. The
    // split matters — a condition on a plain column (size, downloads) is not
    // grouped or aggregated, so SQL rejects it in HAVING; only conditions over
    // the SUMs belong there.
    $measures = [
        'seeders' => [$seeders.' DESC', '', $seeders.' > 0'],
        'leechers' => [$leechers.' DESC', '', $leechers.' > 0'],
        'bandwidth' => [$bandwidth.' DESC', '`t`.`size` IS NOT NULL AND `t`.`downloads` > 0', ''],
        // Worst seeder share first, so the torrent with the most people waiting
        // on the fewest seeders leads.
        'trouble' => [
            '('.$seeders.' / ('.$seeders.' + '.$leechers.')) ASC, '.$leechers.' DESC',
            '',
            $leechers.' > 0 AND ('.$seeders.' / ('.$seeders.' + '.$leechers.')) < 0.25',
        ],
    ];
    [$order, $where, $having] = $measures[$measure] ?? $measures['seeders'];

    $result = mysqli_query(
        $connection,
        'SELECT `t`.`info_hash`, `t`.`name`, `t`.`filename`, `t`.`downloads`, '.
        $seeders.' AS `seeders`, '.$leechers.' AS `leechers`, '.
        'IFNULL('.$bandwidth.', 0) AS `traffic` '.
        'FROM `'.$prefix.'torrents` `t` '.
        'LEFT JOIN `'.$prefix.'peers` `p` ON `p`.`info_hash` = `t`.`info_hash` '.
        ($where === '' ? '' : 'WHERE '.$where.' ').
        'GROUP BY `t`.`info_hash` '.
        ($having === '' ? '' : 'HAVING '.$having.' ').
        'ORDER BY '.$order.' '.
        'LIMIT '.$limit.';',
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
            'seeders' => intval($row['seeders']),
            'leechers' => intval($row['leechers']),
            'downloads' => intval($row['downloads']),
            'bandwidth' => intval($row['bandwidth']),
        ];
    }

    return $rows;
}
