<?php

declare(strict_types=1);

////	db_backup_list
// List the existing backups in backup_dir, newest first, so the admin Backups
// page can show them. One entry per backup:
//   ['name' => basename, 'size' => total bytes, 'mtime' => Unix timestamp,
//    'files' => list<['name' => basename, 'size' => bytes]>]
//
// A backup is normally a dated directory, and 'files' holds the dumps inside it
// (schema.sql plus one per table), sorted so schema comes first — the order a
// restore wants. Single-file dumps written before the split are still listed,
// as one entry with an empty 'files', so an upgraded install can still download
// what it already has.
//
// Returns an empty array when the directory or pattern matches nothing.

/**
 * @param PhoenixSettings $settings
 * @return list<array{name: string, size: int, mtime: int, files: list<array{name: string, size: int}>}>
 */
function db_backup_list(array $settings): array
{
    $backup_dir = ! empty($settings['backup_dir'])
        ? rtrim($settings['backup_dir'], '/').'/'
        : __DIR__.'/../../backups/';

    $backups = [];
    foreach (glob($backup_dir.$settings['db_name'].'.*') ?: [] as $path) {
        $mtime = filemtime($path);
        $entry = ['name' => basename($path), 'size' => 0, 'mtime' => $mtime === false ? 0 : $mtime, 'files' => []];

        if (is_dir($path)) {
            $files = array_merge(glob($path.'/*.sql') ?: [], glob($path.'/*.sql.gz') ?: []);
            if ($files === []) {
                // An empty directory is not a backup worth offering.
                continue;
            }
            foreach ($files as $file) {
                $size = filesize($file);
                $size = $size === false ? 0 : $size;
                $entry['files'][] = ['name' => basename($file), 'size' => $size];
                $entry['size'] += $size;
            }
            // schema first, then the data files alphabetically — restore order.
            usort($entry['files'], static function (array $a, array $b): int {
                $a_schema = str_starts_with($a['name'], 'schema.');
                $b_schema = str_starts_with($b['name'], 'schema.');

                return $a_schema === $b_schema ? strcmp($a['name'], $b['name']) : ($a_schema ? -1 : 1);
            });
        } elseif (preg_match('/\.sql(\.gz)?$/', $path)) {
            $size = filesize($path);
            $entry['size'] = $size === false ? 0 : $size;
        } else {
            continue;
        }

        $backups[] = $entry;
    }

    // Newest first.
    usort($backups, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

    return $backups;
}
