<?php

declare(strict_types=1);

////	admin_backup_delete_action
// Handles the Backups page "Delete" button (process=backup_delete). Removes one
// backup — a dated directory of dumps, or a legacy single-file dump — and
// returns a message for the panel.
//
// The posted name is never joined onto a path directly. It must basename() to
// itself (so it carries no directory component) and then match an entry
// db_backup_list() actually reports, which is the same validation the download
// path uses: an unknown name and a traversal attempt both fall through to the
// same "not found", telling an attacker nothing either way.

/** @param PhoenixSettings $settings */
function admin_backup_delete_action(array $settings, string $name): string
{
    require_once __DIR__.'/../functions/db.backup.list.php';
    require_once __DIR__.'/../functions/db.backup.remove.php';

    if ($name === '' || basename($name) !== $name) {
        return 'Backup not found.';
    }

    $listed = false;
    foreach (db_backup_list($settings) as $backup) {
        if ($backup['name'] === $name) {
            $listed = true;
            break;
        }
    }
    if (! $listed) {
        return 'Backup not found.';
    }

    $backup_dir = ! empty($settings['backup_dir'])
        ? rtrim($settings['backup_dir'], '/').'/'
        : __DIR__.'/../../backups/';

    if (! db_backup_remove($backup_dir.$name)) {
        // db_backup_remove() only unlinks .sql/.sql.gz, so a directory holding
        // anything else is left alone rather than being cleared out.
        return 'Could not delete '.$name.' — it may hold files Phoenix did not write.';
    }

    return 'Deleted '.$name.'.';
}
