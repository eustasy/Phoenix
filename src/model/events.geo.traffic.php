<?php

declare(strict_types=1);

////	events_geo_traffic
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

/**
 * @param PhoenixSettings $settings
 * @return array<string, int>
 */
function events_geo_traffic(mysqli $connection, array $settings): array
{
    $prefix = $settings['db_prefix'];

    $result = mysqli_query(
        $connection,
        'SELECT `e`.`country`, SUM(IFNULL(`t`.`size`, 0)) AS `bytes` '.
        'FROM `'.$prefix.'events` `e` '.
        'LEFT JOIN `'.$prefix.'torrents` `t` ON `t`.`info_hash` = `e`.`info_hash` '.
        'WHERE `e`.`event` = \'completed\' AND `e`.`country` <> \'\' '.
        'GROUP BY `e`.`country`;',
    );
    if (! $result instanceof mysqli_result) {
        return [];
    }

    $traffic = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $country = is_string($row['country']) ? strtoupper($row['country']) : '';
        $bytes = intval($row['bytes']);
        if ($country === '' || $bytes <= 0) {
            continue;
        }
        $traffic[$country] = $bytes;
    }

    return $traffic;
}
