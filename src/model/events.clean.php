<?php

declare(strict_types=1);

////	events_clean
// DELETE old events and test sentinels from the stat-tracking ledger.
// Rows older than stats_retention days are pruned; retention 0 keeps
// events forever (only the test sentinels are removed). Retention is a
// data policy, not a collection policy — it applies even when
// stats_enabled has since been turned off.
// Returns true on success, false on failure.

/** @param PhoenixSettings $settings */
function events_clean(mysqli $connection, array $settings, int $time): bool
{
    $sql = 'DELETE FROM `'.$settings['db_prefix'].'events`'.
        ' WHERE `info_hash` LIKE \'__TEST_%\''.
        ' OR `info_hash` = \'DELETEME\'';
    if ($settings['stats_retention'] > 0) {
        $threshold = $time - ($settings['stats_retention'] * 86400);
        $sql .= ' OR `time` < \''.$threshold.'\'';
    }
    $result = mysqli_query($connection, $sql.';');

    return $result !== false;
}
