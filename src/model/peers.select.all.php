<?php

declare(strict_types=1);

////	peers_select_all
// Returns a page of peers across every swarm — the data behind the admin global
// Peers listing. Mirrors peers_select_by_torrent() but drops its WHERE clause,
// adds the info_hash column, and LEFT JOINs torrents so each row carries its
// torrent name (null for an unregistered swarm). Newest-seen first, paged by
// $limit/$offset. $limit and $offset are typed int (clamped here) and inlined,
// so they carry no SQL-injection risk — unlike the hex info_hash/peer_id, no
// untrusted string reaches the query. Returns an empty array when there are no
// peers in range.

/**
 * @param PhoenixSettings $settings
 * @return list<array{
 *     info_hash: string,
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
 *     name: string|null,
 * }>
 */
function peers_select_all(mysqli $connection, array $settings, int $limit, int $offset): array
{
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $prefix = $settings['db_prefix'];

    $result = mysqli_query(
        $connection,
        'SELECT p.`info_hash`, p.`peer_id`, p.`ipv4`, p.`ipv6`, p.`portv4`, p.`portv6`, '.
        'p.`uploaded`, p.`downloaded`, p.`left`, p.`state`, p.`updated`, t.`name` '.
        'FROM `'.$prefix.'peers` p '.
        'LEFT JOIN `'.$prefix.'torrents` t ON t.`info_hash` = p.`info_hash` '.
        'ORDER BY p.`updated` DESC '.
        'LIMIT '.$limit.' OFFSET '.$offset.';',
    );
    if (! $result instanceof mysqli_result) {
        tracker_error('Unable to get peers.');
    }

    $peers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $peers[] = [
            'info_hash' => is_string($row['info_hash']) ? $row['info_hash'] : '',
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
            'name' => is_string($row['name']) ? $row['name'] : null,
        ];
    }

    return $peers;
}
