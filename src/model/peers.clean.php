<?php

declare(strict_types=1);

////	peers_clean
// DELETE stale peers (updated < threshold) and test sentinels.
// Removes peers that have not announced within 3x the announce interval.
// Also purges rows with test-reserved prefixes/values left by the test suite.
// Returns true on success, false on failure.

function peers_clean(mysqli $connection, array $settings, int $threshold): bool
{
    $result = mysqli_query(
        $connection,
        'DELETE FROM `'.$settings['db_prefix'].'peers`'.
        ' WHERE `updated` < \''.$threshold.'\''.
        ' OR `info_hash` LIKE \'__TEST_%\''.
        ' OR `info_hash` = \'DELETEME\''.
        ' OR `peer_id` LIKE \'__TEST_%\''.
        ' OR `peer_id` = \'DELETEME\';',
    );

    return $result !== false;
}
