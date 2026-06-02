<?php

declare(strict_types=1);

////	peers_count_rate
// SELECT COUNT announces from one IP in time window.
// Counts peers matching the same info_hash and IP (v4 or v6), but different peer_id,
// updated after threshold. Used for rate limiting to prevent rapid fake-peer injection.
// Returns int count.

function peers_count_rate(mysqli $connection, array $settings, array $peer, int $threshold): int
{
    require_once __DIR__.'/db.fetch.once.php';

    $ip_parts = [];
    if ($peer['ipv4']) {
        $ip_parts[] = '`ipv4`=\''.$peer['ipv4'].'\'';
    }
    if ($peer['ipv6']) {
        $ip_parts[] = '`ipv6`=\''.$peer['ipv6'].'\'';
    }

    if (empty($ip_parts)) {
        return 0;
    }

    $rate = db_fetch_once(
        $connection,
        'SELECT COUNT(*) AS `count` FROM `'.$settings['db_prefix'].'peers` '.
        'WHERE `info_hash`=\''.$peer['info_hash'].'\' '.
        'AND ('.implode(' OR ', $ip_parts).') '.
        'AND `peer_id`!=\''.$peer['peer_id'].'\' '.
        'AND `updated`>'.$threshold.';',
    );

    return $rate ? intval($rate['count']) : 0;
}
