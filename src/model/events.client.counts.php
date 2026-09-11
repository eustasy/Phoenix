<?php

declare(strict_types=1);

////	events_client_counts
// Completed downloads by client family, all-time, from the events ledger's
// stored labels — the historical counterpart to peers_client_breakdown(), which
// reads the live swarm.
//
// Normalised to FAMILY, deliberately. The ledger stores whatever label was
// written at the time, and a tracker that has been running a while will carry
// both formats: rows written before versions were recorded hold "Transmission",
// newer ones "Transmission 4.1.3.0". Grouping those separately would split one
// client across two bars and make the history unreadable, so the version is
// dropped here and only the live view offers it.
//
// Returns ['Transmission' => 364237, …], highest first, merging labels that
// resolve to the same family. Empty when the ledger holds no labelled
// completions.

/**
 * @param PhoenixSettings $settings
 * @return array<string, int>
 */
function events_client_counts(mysqli $connection, array $settings): array
{
    require_once __DIR__.'/../functions/stats.client.family.php';

    $result = mysqli_query(
        $connection,
        'SELECT `client`, COUNT(*) AS `n` '.
        'FROM `'.$settings['db_prefix'].'events` '.
        'WHERE `event` = \'completed\' AND `client` <> \'\' '.
        'GROUP BY `client`;',
    );
    if (! $result instanceof mysqli_result) {
        return [];
    }

    $families = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $label = is_string($row['client']) ? $row['client'] : '';
        if ($label === '') {
            continue;
        }
        $family = stats_client_family($label)['family'];
        $families[$family] = ($families[$family] ?? 0) + intval($row['n']);
    }

    arsort($families);

    return $families;
}
