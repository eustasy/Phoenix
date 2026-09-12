<?php

declare(strict_types=1);

////	db_check
// Run CHECK TABLE over the Phoenix tables — an integrity scan, offered as a
// Utilities action rather than run on a schedule.
//
// It is deliberately not part of the routine maintenance run. On InnoDB a CHECK
// is a full scan of every row and index, and InnoDB already verifies page
// checksums whenever it reads a page, so corruption surfaces on its own during
// normal use. Paying for a full scan of the events ledger every few minutes to
// re-ask a question the storage engine answers continuously is not a trade
// worth making; running it by hand when something looks wrong is.
//
// Logs a `check` task run on success. Returns false when any table reported an
// error — which, for this statement, is the interesting outcome.

/**
 * @param PhoenixSettings $settings
 * @param string $source who triggered the run: 'admin' or 'cron'
 */
function db_check(mysqli $connection, array $settings, int $time, string $source = 'admin'): bool
{
    require_once __DIR__.'/db.maintenance.php';
    require_once __DIR__.'/task.log.php';

    $ok = db_maintenance($connection, $settings, 'CHECK', ['events', 'peers', 'tasks', 'task_runs', 'torrents']);

    if ($ok) {
        task_log($connection, $settings, 'check', $time, $source);
    }

    return $ok;
}
