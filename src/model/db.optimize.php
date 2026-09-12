<?php

declare(strict_types=1);

////	db_optimize
// Rebuild the Phoenix tables to reclaim space left behind by deleted rows.
// On InnoDB, OPTIMIZE TABLE maps to ALTER TABLE … FORCE: a full table rebuild
// that repacks sparsely-filled pages and then re-analyses. It reports a `note`
// saying so, which is routine and not a failure.
//
// This is the expensive one, and it belongs on a slow schedule
// (bin/optimize-database.php) rather than the frequent cleanup run. Measured:
// after deleting 118,800 of 120,000 rows, ANALYZE left the table at 25.7MB and
// OPTIMIZE brought it to 0.2MB — so it earns its cost after a bulk deletion
// (an events prune, a large peer cleanup) and wastes it on a table in steady
// state, where InnoDB simply reuses the pages its own deletes freed.
//
// REPAIR TABLE is deliberately absent. On MariaDB it reports `status: OK` on an
// InnoDB table and performs a full rebuild of its own — verified by watching
// information_schema.TABLES.CREATE_TIME change across it — so pairing it with
// OPTIMIZE rebuilds every table twice for one table's worth of benefit.
//
// `events` is excluded: it is by far the largest table and is append-mostly, so
// a rebuild costs the most and reclaims the least. Prune it with
// stats_retention and optimize it by hand afterwards.
//
// $table optimizes one extra table by name; $and_default includes the standard
// set. Logs an `optimize` task run on success. Returns false when any table
// reported an error.

/**
 * @param PhoenixSettings $settings
 * @param string $source who triggered the run: 'admin' or 'cron'
 */
function db_optimize(mysqli $connection, array $settings, int $time, string|false $table = false, bool $and_default = true, string $source = 'admin'): bool
{
    require_once __DIR__.'/db.maintenance.php';
    require_once __DIR__.'/task.log.php';

    $tables = [];
    if ($table) {
        $tables[] = $table;
    }
    if ($and_default) {
        array_push($tables, 'peers', 'tasks', 'task_runs', 'torrents');
    }

    if ($tables === []) {
        return true;
    }

    $ok = db_maintenance($connection, $settings, 'OPTIMIZE', $tables);

    if ($ok) {
        task_log($connection, $settings, 'optimize', $time, $source);
    }

    return $ok;
}
