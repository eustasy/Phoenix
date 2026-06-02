<?php

declare(strict_types=1);

////	stats_fetch_peer_counts
// Fetch peer statistics from the database.
// Returns array with seeders, leechers, and torrent count, or false on failure.
/**
 * @param array<string, mixed> $settings
 * @return array<string, mixed>|false
 */
function stats_fetch_peer_counts(mysqli $connection, array $settings): array|false
{
    require_once __DIR__.'/db.fetch.once.php';

    $sql = 'SELECT '.
        // select seeders and leechers (COALESCE handles NULL from SUM on empty set)
        'COALESCE(SUM(`state`=\'1\'), 0) AS `seeders`, '.
        'COALESCE(SUM(`state`=\'0\'), 0) AS `leechers`, '.
        // unique torrents
        'COUNT(DISTINCT info_hash) AS `torrents` '.
        // from peers
        'FROM `'.$settings['db_prefix'].'peers`;';

    return db_fetch_once($connection, $sql);
}
