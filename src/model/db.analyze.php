<?php

declare(strict_types=1);

////	db_analyze
// Recalculate index statistics for the Phoenix tables, so the query planner
// keeps choosing sensible plans as the data shifts. Cheap — measured at 1.6ms
// across a table InnoDB reported as 25MB — which is what makes it safe to run
// on the frequent maintenance schedule.
//
// This does NOT reclaim space. ANALYZE updates statistics and nothing else:
// after deleting 118,800 of 120,000 rows the table still measured 25.7MB, and
// only OPTIMIZE (db_optimize) brought it down to 0.2MB. The two are not
// interchangeable; see LIMITS.md.
//
// InnoDB also recalculates on its own once roughly 10% of a table's rows have
// changed (innodb_stats_auto_recalc), so this mostly earns its place right
// after a cleanup pass has deleted in bulk, when the automatic trigger has not
// fired yet but the statistics are already stale.
//
// Logs an `analyze` task run on success. Returns false when any table reported
// an error.

/**
 * @param PhoenixSettings $settings
 * @param string $source who triggered the run: 'admin' or 'cron'
 */
function db_analyze(mysqli $connection, array $settings, int $time, string $source = 'cron'): bool
{
    require_once __DIR__.'/db.maintenance.php';
    require_once __DIR__.'/task.log.php';

    $ok = db_maintenance($connection, $settings, 'ANALYZE', ['events', 'peers', 'tasks', 'task_runs', 'torrents']);

    if ($ok) {
        task_log($connection, $settings, 'analyze', $time, $source);
    }

    return $ok;
}
