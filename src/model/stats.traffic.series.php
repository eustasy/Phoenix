<?php

declare(strict_types=1);

////	stats_traffic_series
// Traffic over time, bucketed, for the Traffic page's chart and the dashboard's
// summary chart.
//
// Derived from the events ledger: each logged completion is joined to its
// torrent's size, so a bucket's bytes are the sizes of everything that finished
// downloading in it. That makes this an ESTIMATE — it assumes every completion
// transferred the file exactly once, so it counts no partial downloads, no
// repeat downloads, and no upload beyond the first copy. It is the only
// time-stamped traffic figure the tracker has; the live peers table carries
// real client-reported byte counters but no history.
//
// Requires stats_enabled and 'completed' in stats_events; returns an empty
// array otherwise, which is also what an install gets before its ledger has
// anything in it.
//
// $days bounds the window so the query can use the `time` index — an unbounded
// scan of a long-lived ledger is a full table read. $bucket is the bucket width
// in seconds (86400 daily, 2592000 monthly-ish). Both are clamped ints and
// inlined; no untrusted string reaches the query.
//
// The bucket in progress is dropped. A day (or week, or month) that is only
// part-elapsed always reads as a fall, which looks like traffic collapsing
// rather than the period being incomplete — the most recent point on a chart is
// the one people read hardest, and it is the one that is guaranteed wrong. Only
// a bucket containing the current time is dropped, so a historical window keeps
// its final point.
//
// Returns [['time' => bucket start, 'completions' => int, 'bytes' => int], …]
// oldest first, with empty buckets omitted — the chart spans them itself rather
// than the query inventing rows for quiet days.

/**
 * @param PhoenixSettings $settings
 * @return list<array{time: int, completions: int, bytes: int}>
 */
function stats_traffic_series(mysqli $connection, array $settings, int $days = 90, int $bucket = 86400): array
{
    $prefix = $settings['db_prefix'];
    $days = max(1, min(4000, $days));
    $bucket = max(3600, min(31536000, $bucket));

    $result = mysqli_query(
        $connection,
        'SELECT FLOOR(`e`.`time` / '.$bucket.') * '.$bucket.' AS `bucket`, '.
        'COUNT(*) AS `completions`, '.
        'SUM(IFNULL(`t`.`size`, 0)) AS `bytes` '.
        'FROM `'.$prefix.'events` `e` '.
        'LEFT JOIN `'.$prefix.'torrents` `t` ON `t`.`info_hash` = `e`.`info_hash` '.
        'WHERE `e`.`event` = \'completed\' '.
        'AND `e`.`time` >= UNIX_TIMESTAMP() - '.($days * 86400).' '.
        'GROUP BY `bucket` '.
        'ORDER BY `bucket` ASC;',
    );
    if (! $result instanceof mysqli_result) {
        return [];
    }

    $current = intdiv(time(), $bucket) * $bucket;

    $series = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $start = intval($row['bucket']);
        if ($start >= $current) {
            continue;
        }
        $series[] = [
            'time' => $start,
            'completions' => intval($row['completions']),
            'bytes' => intval($row['bytes']),
        ];
    }

    return $series;
}
