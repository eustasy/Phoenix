<?php

declare(strict_types=1);

////	events_geo_counts
// Aggregate completed-download events by country for the admin Geography page.
// Reads the privacy-preserving events ledger, which already stores a coarse ISO
// country code (derived from the peer's IP at the time and never the IP itself).
// Only populated for the period stats_enabled + stats_geo have been on. Returns
// ['US' => 1340, …] (countries with at least one geo-tagged completion), or an
// empty array when the ledger carries no such rows.

/**
 * @param PhoenixSettings $settings
 * @return array<string, int>
 */
function events_geo_counts(mysqli $connection, array $settings): array
{
    $result = mysqli_query(
        $connection,
        'SELECT `country`, COUNT(*) AS `n` '.
        'FROM `'.$settings['db_prefix'].'events` '.
        'WHERE `event` = \'completed\' AND `country` <> \'\' '.
        'GROUP BY `country`;',
    );
    if (! $result instanceof mysqli_result) {
        tracker_error('Unable to get events.');
    }

    $counts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $country = is_string($row['country']) ? strtoupper($row['country']) : '';
        if ($country === '') {
            continue;
        }
        $counts[$country] = intval($row['n']);
    }

    return $counts;
}
