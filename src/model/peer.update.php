<?php

declare(strict_types=1);

////	peer_update
// UPDATE timestamp and transfer counters for a re-announcing peer.
// Used when peer address and state are unchanged (no migration or completion).
// Returns true on success, calls tracker_error() on failure.

/**
 * @param PhoenixSettings $settings
 * @param array<string, mixed> $peer
 */
function peer_update(mysqli $connection, array $settings, int $time, array $peer): true
{
    $peer_update = mysqli_execute_query(
        $connection,
        'UPDATE `'.$settings['db_prefix'].'peers` '.
        'SET `updated`=?, `uploaded`=?, `downloaded`=?, `left`=? '.
        'WHERE `info_hash`=? AND `peer_id`=?;',
        [$time, $peer['uploaded'], $peer['downloaded'], $peer['left'], $peer['info_hash'], $peer['peer_id']],
    );
    if (! $peer_update) {
        tracker_error('Failed to update peers last access.');
    }

    return true;
}
