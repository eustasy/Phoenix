<?php

declare(strict_types=1);

////	events_geo_bandwidth
// Aggregate traffic by country for the admin Geography page: the same
// completed-download events events_geo_counts() counts, weighted by the size of
// the torrent each one finished. So the two metrics are one dataset read two
// ways — how many downloads a country completed, and how many bytes that moved.
//
// That makes this the same ESTIMATE the rest of the all-time traffic figures
// are: each completion counted as exactly one full transfer, so no partial and
// no repeat downloads. A completion whose torrent has no recorded size, or
// whose torrent has since been removed, contributes nothing.
//
// Only populated for the period stats_enabled + stats_geo have been on, since
// the country code is stored on the event row. Returns ['US' => 1340000000, …]
// in bytes, or an empty array when the ledger carries no geo-tagged
// completions.
//
// Deliberately does NOT join torrents. Joining sizes per event costs a lookup
// for every row of a ledger that only grows — measured at 2.0s against 1.26M
// events, against 0.98s for the same aggregation without it. Grouping by
// (country, info_hash) instead returns one row per pair — a few thousand at
// most, since a tracker has far fewer torrents than completions — and the sizes
// are applied here against the torrents table read once.

/**
 * @param PhoenixSettings $settings
 * @return array<string, int>
 */
function events_geo_bandwidth(mysqli $connection, array $settings): array
{
    $prefix = $settings['db_prefix'];

    // Sizes first: one small read, keyed by hash.
    $sizes = [];
    $result = mysqli_query(
        $connection,
        'SELECT `info_hash`, `size` FROM `'.$prefix.'torrents` WHERE `size` IS NOT NULL;',
    );
    if (! $result instanceof mysqli_result) {
        return [];
    }
    while ($row = mysqli_fetch_assoc($result)) {
        if (is_string($row['info_hash'])) {
            $sizes[$row['info_hash']] = intval($row['size']);
        }
    }
    if ($sizes === []) {
        return [];
    }

    $result = mysqli_query(
        $connection,
        'SELECT `country`, `info_hash`, COUNT(*) AS `n` '.
        'FROM `'.$prefix.'events` '.
        'WHERE `event` = \'completed\' AND `country` <> \'\' '.
        'GROUP BY `country`, `info_hash`;',
    );
    if (! $result instanceof mysqli_result) {
        return [];
    }

    $bandwidth = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $country = is_string($row['country']) ? strtoupper($row['country']) : '';
        $hash = is_string($row['info_hash']) ? $row['info_hash'] : '';
        // A completion whose torrent has been removed, or has no recorded size,
        // contributes nothing — the same as the join's IFNULL(size, 0).
        if ($country === '' || ! isset($sizes[$hash])) {
            continue;
        }
        $bandwidth[$country] = ($bandwidth[$country] ?? 0) + ($sizes[$hash] * intval($row['n']));
    }

    return array_filter($bandwidth, static fn (int $bytes): bool => $bytes > 0);
}
