<?php

declare(strict_types=1);

////	peers_top
// The dashboard's peer mini-tables: the few peers moving the most bytes.
// Distinct from torrents_top(), which ranks TORRENTS by how many peers they
// have — this ranks the peers themselves.
//
// $measure is one of:
//   'seeders'  — peers in the seeding state, by bytes uploaded
//   'leechers' — peers still downloading, by bytes downloaded
//   'bandwidth'  — every peer, by bytes uploaded, whatever its state: a leecher
//                that is also serving belongs in a chart of who is moving the
//                most data, which the state-filtered measures would hide
//
// Both figures are client-reported: a peer announces its own cumulative
// uploaded/downloaded, so they are trustworthy enough to rank by and not
// trustworthy enough to bill on. They also reset when a client restarts, so
// this is "most active right now", not an all-time league table.
//
// Rows carry the address rather than the peer_id, because that is what the
// listing filters on — a card row links to that peer's swarms. Both counters
// come back regardless of which one ordered the rows, so a caller can show the
// ratio (a peer pulling 400 MB while serving nothing reads differently from one
// doing both).
//
// Returns a list of ['address', 'peer_id', 'info_hash', 'name', 'bytes',
// 'uploaded', 'downloaded', 'state'], largest first, empty when none qualifies.

/**
 * @param PhoenixSettings $settings
 * @return list<array{address: string, peer_id: string, info_hash: string, name: string|null, bytes: int, uploaded: int, downloaded: int, state: int}>
 */
function peers_top(mysqli $connection, array $settings, string $measure = 'seeders', int $limit = 5): array
{
    $prefix = $settings['db_prefix'];
    $limit = max(1, min(50, $limit));

    // Literals from a fixed map — no untrusted string reaches the query. A null
    // state means "any", for the bandwidth ranking.
    $measures = [
        'seeders' => ['`p`.`uploaded`', '1'],
        'leechers' => ['`p`.`downloaded`', '0'],
        'bandwidth' => ['`p`.`uploaded`', null],
    ];
    [$column, $state] = $measures[$measure] ?? $measures['seeders'];

    $result = mysqli_query(
        $connection,
        'SELECT `p`.`peer_id`, `p`.`info_hash`, `p`.`ipv4`, `p`.`ipv6`, `p`.`state`, '.
        '`p`.`uploaded`, `p`.`downloaded`, '.
        $column.' AS `bytes`, `t`.`name` '.
        'FROM `'.$prefix.'peers` `p` '.
        'LEFT JOIN `'.$prefix.'torrents` `t` ON `t`.`info_hash` = `p`.`info_hash` '.
        'WHERE '.($state === null ? '' : '`p`.`state` = \''.$state.'\' AND ').
        $column.' > 0 '.
        'ORDER BY '.$column.' DESC '.
        'LIMIT '.$limit.';',
    );
    if (! $result instanceof mysqli_result) {
        return [];
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $ipv4 = is_string($row['ipv4']) ? $row['ipv4'] : '';
        $ipv6 = is_string($row['ipv6']) ? $row['ipv6'] : '';
        $rows[] = [
            'address' => $ipv4 !== '' ? $ipv4 : $ipv6,
            'peer_id' => is_string($row['peer_id']) ? $row['peer_id'] : '',
            'info_hash' => is_string($row['info_hash']) ? $row['info_hash'] : '',
            'name' => is_string($row['name']) ? $row['name'] : null,
            'bytes' => intval($row['bytes']),
            'uploaded' => intval($row['uploaded']),
            'downloaded' => intval($row['downloaded']),
            'state' => intval($row['state']),
        ];
    }

    return $rows;
}
