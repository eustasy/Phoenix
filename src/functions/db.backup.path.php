<?php

declare(strict_types=1);

////	db_backup_path
// Resolve the absolute filesystem path of a backup file for download,
// validating the request against what db_backup_list() actually reports rather
// than against the filesystem — an unknown name is indistinguishable from a
// traversal attempt, and both get the same false.
//
// $name is the backup (a dated directory, or a legacy single-file dump).
// $file is the dump inside that directory, and is required for a directory
// backup and rejected for a legacy one.
//
// Each segment is checked separately: basename($segment) !== $segment rejects
// anything carrying a directory component ("../etc/passwd", "sub/file", a bare
// "..") before the list is consulted at all, so no combination of the two can
// escape backup_dir. Returns false on any mismatch.

/**
 * @param PhoenixSettings $settings
 */
function db_backup_path(array $settings, string $name, string $file = ''): string|false
{
    require_once __DIR__.'/db.backup.list.php';

    // Defense in depth: neither segment may contain a directory component.
    if ($name === '' || basename($name) !== $name) {
        return false;
    }
    if ($file !== '' && basename($file) !== $file) {
        return false;
    }

    $backup_dir = ! empty($settings['backup_dir'])
        ? rtrim($settings['backup_dir'], '/').'/'
        : __DIR__.'/../../backups/';

    foreach (db_backup_list($settings) as $backup) {
        if ($backup['name'] !== $name) {
            continue;
        }

        // A legacy single-file dump is downloaded as itself and names no file.
        if ($backup['files'] === []) {
            return $file === '' ? $backup_dir.$name : false;
        }

        // A directory backup must name one of the files it actually contains.
        foreach ($backup['files'] as $candidate) {
            if ($candidate['name'] === $file) {
                return $backup_dir.$name.'/'.$file;
            }
        }

        return false;
    }

    return false;
}
