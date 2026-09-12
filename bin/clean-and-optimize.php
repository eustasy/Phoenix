<?php

declare(strict_types=1);

// Scheduled maintenance entry point: prune stale rows, then refresh index
// statistics. Both are cheap, so this wants to run often.
//
// It does NOT rebuild tables. OPTIMIZE TABLE is a full rebuild on InnoDB and
// lives in bin/optimize-database.php on its own, slower schedule — running it
// every few minutes rebuilt tables that had nothing to reclaim.
//
// Only runs when clean_with_cron is enabled; that setting disables the per-request fallback
// in public/announce.php so the announce path doesn't pay the cleanup overhead on any requests.
require_once __DIR__.'/../src/phoenix.php';

if ($settings['clean_with_cron']) {
    require_once __DIR__.'/../src/functions/task.clean.php';
    require_once __DIR__.'/../src/model/db.analyze.php';
    task_clean($connection, $settings, $time, 'cron');
    db_analyze($connection, $settings, $time, 'cron');
}
