<?php

declare(strict_types=1);

////	db_backup_remove
// Delete one backup — a dated directory of dumps, or a legacy single-file dump.
// Split out of db_backup()'s rotation because this is the one part of the backup
// engine that destroys data, and it is worth being able to read and test on its
// own.
//
// Deliberately not a recursive delete. A backup directory holds .sql/.sql.gz
// files and nothing else, so this unlinks exactly those and then rmdir()s, which
// fails harmlessly if anything unexpected is in there rather than removing it.
// $path must be one db_backup_list() reported, so a caller cannot aim this at an
// arbitrary directory.
//
// Returns true when nothing of the backup remains.

function db_backup_remove(string $path): bool
{
    if (is_file($path)) {
        return @unlink($path);
    }

    if (! is_dir($path)) {
        // Already gone; the caller's goal is met either way.
        return true;
    }

    foreach (glob(rtrim($path, '/').'/*.sql') ?: [] as $file) {
        @unlink($file);
    }
    foreach (glob(rtrim($path, '/').'/*.sql.gz') ?: [] as $file) {
        @unlink($file);
    }

    // Fails while anything the patterns above did not match is still present —
    // which is the safe outcome: an unrecognised file keeps its directory.
    return @rmdir($path);
}
