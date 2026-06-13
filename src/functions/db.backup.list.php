<?php

declare(strict_types=1);

////	db_backup_list
// List the existing database backups in backup_dir, newest first, so the admin
// Backups page can show them. Matches the same glob db_backup() rotates over
// (<backup_dir><db_name>.*.sql). Returns one entry per file:
//   ['name' => basename, 'size' => bytes, 'mtime' => Unix timestamp]
// Returns an empty array when the directory or pattern matches nothing.

/**
 * @param PhoenixSettings $settings
 * @return list<array{name: string, size: int, mtime: int}>
 */
function db_backup_list(array $settings): array
{
    $backup_dir = ! empty($settings['backup_dir'])
        ? rtrim($settings['backup_dir'], '/').'/'
        : __DIR__.'/../../backups/';

    $backups = [];
    foreach (glob($backup_dir.$settings['db_name'].'.*.sql') ?: [] as $path) {
        $size = filesize($path);
        $mtime = filemtime($path);
        $backups[] = [
            'name' => basename($path),
            'size' => $size === false ? 0 : $size,
            'mtime' => $mtime === false ? 0 : $mtime,
        ];
    }

    // Newest first.
    usort($backups, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

    return $backups;
}
