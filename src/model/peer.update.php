<?php

declare(strict_types=1);

////	peer_update
// UPDATE timestamp and transfer counters for a re-announcing peer.
// Used when peer address and state are unchanged (no migration or completion).
// Returns true on success, calls tracker_error() on failure.

function peer_update(mysqli $connection, array $settings, int $time, array $peer): true
{
    $peer_update = mysqli_query(
        $connection,
        'UPDATE `'.$settings['db_prefix'].'peers` '.
        'SET `updated`=\''.$time.'\', `uploaded`=\''.$peer['uploaded'].'\', `downloaded`=\''.$peer['downloaded'].'\', `left`=\''.$peer['left'].'\' '.
        'WHERE `info_hash`=\''.$peer['info_hash'].'\' AND `peer_id`=\''.$peer['peer_id'].'\';',
    );
    if (! $peer_update) {
        tracker_error('Failed to update peers last access.');
    }

    return true;
}
