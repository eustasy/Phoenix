<?php

declare(strict_types=1);

////	peers_client_counts
// Count active peers by client, for the dashboard's Top Clients card and the
// client chart.
//
// The client label does not exist in the database — it is derived from peer_id
// per request, and deliberately never stored. So this groups on the peer_id
// PREFIX in SQL, which is where the client and version live, and labels the
// result in PHP afterwards. That keeps the work proportional to the number of
// distinct clients rather than the number of peers: a tracker with 100k peers
// still returns a few dozen prefixes to label, not 100k rows to read.
//
// peer_id is stored hex-encoded, so 16 hex chars is the first 8 bytes — enough
// for every Azureus-style (`-TR4060-`) and Shadow-style prefix.
//
// Returns ['Transmission 4.0.6.0' => 312, …], highest first, merging prefixes
// that resolve to the same label.

/**
 * @param PhoenixSettings $settings
 * @return array<string, int>
 */
function peers_client_counts(mysqli $connection, array $settings): array
{
    require_once __DIR__.'/../functions/stats.client.detect.php';

    $result = mysqli_query(
        $connection,
        'SELECT LEFT(`peer_id`, 16) AS `prefix`, COUNT(*) AS `n` '.
        'FROM `'.$settings['db_prefix'].'peers` '.
        'GROUP BY `prefix`;',
    );
    if (! $result instanceof mysqli_result) {
        return [];
    }

    $counts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $prefix = is_string($row['prefix']) ? $row['prefix'] : '';
        if ($prefix === '') {
            continue;
        }

        // stats_client_detect() expects a full peer_id; the prefix carries
        // everything it reads, so pad to length rather than reimplement it.
        $label = stats_client_detect(str_pad($prefix, 40, '0'));
        $counts[$label] = ($counts[$label] ?? 0) + intval($row['n']);
    }

    arsort($counts);

    return $counts;
}
