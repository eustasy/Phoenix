<?php

declare(strict_types=1);

////	admin_backup_action
// Handles the Backups page "Run backup now" button (process=backup). Runs the
// shared backup engine and, on success, records the run in the tasks table so
// the dashboard's "Last backup" line reflects it. Returns a message for the
// panel: the engine's error string on failure (e.g. missing mysqldump or an
// unwritable directory), or the written file name on success.

/** @param PhoenixSettings $settings */
function admin_backup_action(mysqli $connection, array $settings, int $time): string
{
    require_once __DIR__.'/../functions/db.backup.php';
    $result = db_backup($settings, $time);

    if (! $result['ok']) {
        return 'Backup failed: '.($result['error'] ?? 'unknown error');
    }

    require_once __DIR__.'/../model/task.log.php';
    task_log($connection, $settings, 'backup', $time, 'admin');

    return 'Backup written: '.basename((string) $result['file']);
}
