<?php

declare(strict_types=1);

////	peers_count_rate
// SELECT COUNT announces from one IP in time window.
// Counts peers matching the same info_hash and IP (v4 or v6), but different peer_id,
// updated after threshold. Used for rate limiting to prevent rapid fake-peer injection.
// Returns int count.

/**
 * @param PhoenixSettings $settings
 * @param array<string, mixed> $peer
 */
function peers_count_rate(mysqli $connection, array $settings, array $peer, int $threshold): int
{
    require_once __DIR__.'/db.fetch.once.php';

    $ip_parts = [];
    $ip_params = [];
    if ($peer['ipv4']) {
        $ip_parts[] = '`ipv4`=?';
        $ip_params[] = $peer['ipv4'];
    }
    if ($peer['ipv6']) {
        $ip_parts[] = '`ipv6`=?';
        $ip_params[] = $peer['ipv6'];
    }

    if (empty($ip_parts)) {
        return 0;
    }

    // Placeholder order must match the SQL: info_hash, then the IP parts,
    // then peer_id, then the freshness threshold.
    $rate = db_fetch_once(
        $connection,
        'SELECT COUNT(*) AS `count` FROM `'.$settings['db_prefix'].'peers` '.
        'WHERE `info_hash`=? '.
        'AND ('.implode(' OR ', $ip_parts).') '.
        'AND `peer_id`!=? '.
        'AND `updated`>?;',
        array_merge([$peer['info_hash']], $ip_params, [$peer['peer_id'], $threshold]),
    );

    return $rate ? intval($rate['count']) : 0;
}
