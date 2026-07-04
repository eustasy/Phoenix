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
    // Degrade a strict-mode DB exception (the PHP 8.1+ mysqli_report default) to
    // a graceful tracker_error() instead of an uncaught 500, mirroring
    // torrent_update / peer_insert. `left` may carry the -1 "unknown" sentinel.
    try {
        $peer_update = mysqli_execute_query(
            $connection,
            'UPDATE `'.$settings['db_prefix'].'peers` '.
            'SET `updated`=?, `uploaded`=?, `downloaded`=?, `left`=? '.
            'WHERE `info_hash`=? AND `peer_id`=?;',
            [$time, $peer['uploaded'], $peer['downloaded'], $peer['left'], $peer['info_hash'], $peer['peer_id']],
        );
    } catch (mysqli_sql_exception $e) {
        if ($settings['report_errors']) {
            require_once __DIR__.'/../functions/phoenix.hook.event.php';
            phoenix_hook_event('error', ['throwable' => $e, 'source' => 'peer_update']);
        }
        $peer_update = false;
    }
    if (! $peer_update) {
        tracker_error('Failed to update peers last access.');
    }

    return true;
}
