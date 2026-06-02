<?php

declare(strict_types=1);

////	peer_select
// SELECT single peer by info_hash + peer_id.
// Returns the peer row as an associative array, or false if no row matches
// (also false on query error — same channel, callers truthy-test).

/**
 * @param PhoenixSettings $settings
 * @param array<string, mixed> $peer
 * @return array<string, float|int|string|null>|false
 */
function peer_select(mysqli $connection, array $settings, array $peer): array|false
{
    require_once __DIR__.'/db.fetch.once.php';

    return db_fetch_once(
        $connection,
        'SELECT * FROM `'.$settings['db_prefix'].'peers` '.
        'WHERE `info_hash`=\''.$peer['info_hash'].'\' AND `peer_id`=\''.$peer['peer_id'].'\';',
    );
}
