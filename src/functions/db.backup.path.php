<?php

declare(strict_types=1);

////	db_backup_path
// Resolve the absolute filesystem path of a named backup file, validating that
// the requested name is exactly one of the basenames db_backup_list() returns.
// Returns false when the name is unknown, contains a path separator, or matches
// no listed backup — preventing path traversal in all forms.

/**
 * @param PhoenixSettings $settings
 */
function db_backup_path(array $settings, string $name): string|false
{
    require_once __DIR__.'/db.backup.list.php';

    // Defense in depth: reject anything that contains a directory component
    // before even consulting the list (catches "../etc/passwd", "sub/file", …).
    if (basename($name) !== $name) {
        return false;
    }

    $backup_dir = ! empty($settings['backup_dir'])
        ? rtrim($settings['backup_dir'], '/').'/'
        : __DIR__.'/../../backups/';

    foreach (db_backup_list($settings) as $backup) {
        if ($backup['name'] === $name) {
            return $backup_dir.$name;
        }
    }

    return false;
}
