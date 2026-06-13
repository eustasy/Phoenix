<?php

declare(strict_types=1);

// Scheduled database backup with optional rotation. Thin wrapper around
// db_backup() — the engine lives in src/functions/db.backup.php so the admin
// Backups page can run the same dump. Cron behaviour is unchanged: silent on
// success (exit 0), prints the error and exits 1 on failure.
require_once __DIR__.'/../src/phoenix.php';
require_once __DIR__.'/../src/functions/db.backup.php';

$result = db_backup($settings, $time);

if (! $result['ok']) {
    echo $result['error'].PHP_EOL;
    exit(1);
}
