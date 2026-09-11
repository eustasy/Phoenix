<?php

declare(strict_types=1);

////	torrents_traffic
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
// $measure selects between them; anything else falls back to 'events'. $limit is
// clamped and inlined. Returns rows ordered by that measure, highest first,
// carrying both figures so the view can show the other as context.

/**
 * @param PhoenixSettings $settings
 * @return list<array{info_hash: string, name: string|null, size: int, downloads: int, estimated: int, uploaded: int, downloaded: int, peers: int}>
 */
function torrents_traffic(mysqli $connection, array $settings, string $measure = 'events', int $limit = 100): array
{
    $prefix = $settings['db_prefix'];
    $limit = max(1, min(500, $limit));

    $estimated = 'IFNULL(`t`.`size`, 0) * `t`.`downloads`';
    $uploaded = 'IFNULL(SUM(`p`.`uploaded`), 0)';

    $order = $measure === 'peers' ? $uploaded.' DESC' : $estimated.' DESC';

    $result = mysqli_query(
        $connection,
        'SELECT `t`.`info_hash`, `t`.`name`, IFNULL(`t`.`size`, 0) AS `size`, `t`.`downloads`, '.
        $estimated.' AS `estimated`, '.
        $uploaded.' AS `uploaded`, '.
        'IFNULL(SUM(`p`.`downloaded`), 0) AS `downloaded`, '.
        'COUNT(`p`.`peer_id`) AS `peers` '.
        'FROM `'.$prefix.'torrents` `t` '.
        'LEFT JOIN `'.$prefix.'peers` `p` ON `p`.`info_hash` = `t`.`info_hash` '.
        'GROUP BY `t`.`info_hash` '.
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
