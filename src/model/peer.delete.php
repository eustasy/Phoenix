<?php

declare(strict_types=1);

////	peer_delete
// DELETE single peer row by info_hash + peer_id.
// Used when a peer announces with event=stopped.
// Returns true on success, calls tracker_error() on failure.

/**
 * @param PhoenixSettings $settings
 * @param array<string, mixed> $peer
 */
function peer_delete(mysqli $connection, array $settings, array $peer): true
{
    $peer_delete = mysqli_execute_query(
        $connection,
        'DELETE FROM `'.$settings['db_prefix'].'peers` '.
        'WHERE info_hash=? AND peer_id=?;',
        [$peer['info_hash'], $peer['peer_id']],
    );
    if (! $peer_delete) {
        tracker_error('Failed to remove peer.');
    }

    return true;
}
