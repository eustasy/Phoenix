<?php

declare(strict_types=1);

////	events_client_breakdown
// Completed downloads by client family, then by version, from the events
// ledger's stored labels — the historical counterpart to
// peers_client_breakdown(), which reads the live swarm.
//
// Versions are only as good as what was recorded. A tracker that has been
// running a while carries labels from more than one era of its own detector:
// rows written before versions were recorded hold "Transmission", newer ones
// "Transmission 4.1.3.0". Both fold into the same family, the unversioned ones
// under a '' version, so a client is one bar with a known-version part and a
// remainder rather than two unrelated bars.
//
// '?Torrent' is repaired to 'µTorrent'. Labels were written into a latin1
// column, and a detector emitting a UTF-8 'µ' (0xC2 0xB5) had it replaced with
// a literal '?' on insert — the same client under two names, tens of thousands
// of rows apart. The correctly-encoded 0xB5 form needs no repair.
//
// Returns ['Transmission' => ['' => 364237, '4.1.3.0' => 2], …], families
// highest first and versions highest first within each. Empty when the ledger
// holds no labelled completions.

/**
 * @param PhoenixSettings $settings
 * @return array<string, array<string, int>>
 */
function events_client_breakdown(mysqli $connection, array $settings): array
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

        // Repair the mangled µ before splitting, so both spellings land in one
        // family rather than sorting apart alphabetically.
        if (str_starts_with($label, '?Torrent')) {
            $label = 'µTorrent'.substr($label, 8);
        }

        ['family' => $family, 'version' => $version] = stats_client_family($label);
        $families[$family][$version] = ($families[$family][$version] ?? 0) + intval($row['n']);
    }

    uasort($families, static fn (array $a, array $b): int => array_sum($b) <=> array_sum($a));
    foreach ($families as &$versions) {
        arsort($versions);
    }
    unset($versions);

    return $families;
}
