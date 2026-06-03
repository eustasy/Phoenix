<?php

declare(strict_types=1);

////	peers_select_active
// SELECT active peers for a torrent (for announce response).
// Returns up to numwant rows excluding the announcer and stale peers.
// WHERE: info_hash, updated > stale_threshold, peer_id != announcer
// ORDER/LIMIT: per strategy (seeders-first, random, etc.)
// Returns array of peer rows, calls tracker_error() on failure.

/**
 * @param PhoenixSettings $settings
 * @param array<string, mixed> $peer
 * @param array{where: string, order: string} $strategy
 * @return array<int, array<string, float|int|string|null>>
 */
function peers_select_active(mysqli $connection, array $settings, array $peer, int $stale_threshold, array $strategy): array
{
    $where = '`info_hash`=? '.
        'AND `peer_id`!=? '.
        'AND `updated`>?'.
        $strategy['where'];
    // LIMIT cannot be a bound parameter in a mysqli prepared statement, so it
    // stays interpolated. numwant is intval'd upstream; narrow + cast to an int
    // here so the literal is injection-safe. The strategy where/order fragments
    // are static SQL with no user values (see peer_select_strategy).
    $limit = is_numeric($peer['numwant']) ? (int) $peer['numwant'] : 0;
    $sql = 'SELECT * FROM `'.$settings['db_prefix'].'peers` '.
        'WHERE '.$where.$strategy['order'].' '.
        'LIMIT '.$limit.';';

    $query = mysqli_execute_query($connection, $sql, [$peer['info_hash'], $peer['peer_id'], $stale_threshold]);
    if (! $query instanceof mysqli_result) {
        tracker_error('Failed to select peers.');
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $rows[] = $row;
    }

    return $rows;
}
