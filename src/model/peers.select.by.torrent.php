<?php

declare(strict_types=1);

////	peers_select_by_torrent
// Returns every peer row for a single info_hash — the data behind the admin
// peer drill-down. Queries the peers table directly (not via torrents), so a
// swarm for an unlisted or entirely unregistered torrent is returned just the
// same. Newest-seen first. Returns an empty array when the swarm has no peers.

/**
 * @param PhoenixSettings $settings
 * @return list<array{
 *     peer_id: string,
 *     ipv4: string,
 *     ipv6: string,
 *     portv4: int,
 *     portv6: int,
 *     uploaded: int,
 *     downloaded: int,
 *     left: int,
 *     state: int,
 *     updated: int,
 * }>
 */
function peers_select_by_torrent(mysqli $connection, array $settings, string $info_hash): array
{
    $result = mysqli_execute_query(
        $connection,
        'SELECT `peer_id`, `ipv4`, `ipv6`, `portv4`, `portv6`, '.
        '`uploaded`, `downloaded`, `left`, `state`, `updated` '.
        'FROM `'.$settings['db_prefix'].'peers` '.
        'WHERE `info_hash` = ? '.
        'ORDER BY `updated` DESC;',
        [$info_hash],
    );
    if (! $result instanceof mysqli_result) {
        tracker_error('Unable to get peers.');
    }

    $peers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $peers[] = [
            'peer_id' => is_string($row['peer_id']) ? $row['peer_id'] : '',
            'ipv4' => is_string($row['ipv4']) ? $row['ipv4'] : '',
            'ipv6' => is_string($row['ipv6']) ? $row['ipv6'] : '',
            'portv4' => intval($row['portv4']),
            'portv6' => intval($row['portv6']),
            'uploaded' => intval($row['uploaded']),
            'downloaded' => intval($row['downloaded']),
            'left' => intval($row['left']),
            'state' => intval($row['state']),
            'updated' => intval($row['updated']),
        ];
    }

    return $peers;
}
