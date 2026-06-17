<?php

declare(strict_types=1);

////	view_announce_json
// Renders a BitTorrent announce response as JSON (for debugging/monitoring).
// Caller is responsible for emitting the Content-Type header.
//
// Arguments:
//   $counts: array{complete: int, incomplete: int} — swarm counts
//   $settings: config array (needs announce_interval, min_interval)
//   $rows: array of peer rows from peers_select_active()
//
// Returns: JSON string.
/**
 * @param array{complete: int, incomplete: int} $counts
 * @param PhoenixSettings $settings
 * @param array<int, array<string, float|int|string|null>> $rows
 */
function view_announce_json(array $counts, array $settings, array $rows, string|false $external_ip = false): string
{
    $peers = [];

    foreach ($rows as $row) {
        $peer_data = [
            'peer_id' => $row['peer_id'],
        ];

        if ($row['ipv4'] != null) {
            $peer_data['ip'] = $row['ipv4'];
            $peer_data['port'] = $row['portv4'];
        } elseif ($row['ipv6'] != null) {
            $peer_data['ip'] = $row['ipv6'];
            $peer_data['port'] = $row['portv6'];
        }

        $peers[] = $peer_data;
    }

    $response = [
        'complete' => $counts['complete'],
        'incomplete' => $counts['incomplete'],
        'interval' => $settings['announce_interval'],
        'min_interval' => $settings['min_interval'],
        'peers' => $peers,
    ];

    // BEP 24: the tracker's view of the client's own public address (parity
    // with the bencode response; here as a human-readable string).
    if ($external_ip !== false) {
        $response['external_ip'] = $external_ip;
    }

    return json_encode($response) ?: '';
}
