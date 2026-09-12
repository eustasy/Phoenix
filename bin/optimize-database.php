<?php

declare(strict_types=1);

// Scheduled table rebuild. Separate from bin/clean-and-optimize.php because the
// two want completely different schedules: cleaning and analysing are cheap and
// want to run often, while OPTIMIZE TABLE is a full rebuild on InnoDB and wants
// to run rarely — daily is plenty, and only really earns its cost after a bulk
// deletion has left space to reclaim.
//
// Runs regardless of clean_with_cron: that setting governs whether the frequent
// cleanup happens on a schedule or falls back to announce time, and a rebuild
// never happens at announce time either way.
//
// Cron behaviour: silent on success (exit 0), prints and exits 1 on failure.
require_once __DIR__.'/../src/phoenix.php';
require_once __DIR__.'/../src/model/db.optimize.php';

if (! db_optimize($connection, $settings, $time, false, true, 'cron')) {
    echo 'Database optimize failed. Check the server error log.'.PHP_EOL;
    exit(1);
}
