<?php

declare(strict_types=1);

////	torrent_select_one
// Fetch a single torrent row by info_hash using a prepared statement.
// Returns false when the torrent is not found or the query fails.
// Returns an associative array with normalized meta fields on success.

/**
 * @param PhoenixSettings $settings
 * @return array{
 *     info_hash: string,
 *     user: string|null,
 *     name: string|null,
 *     size: int,
 *     listed: int,
 *     downloads: int,
 *     filename: string|null,
 *     files: list<array{path: string, length: int}>|null,
 *     trackers: list<string>|null,
 *     webseeds: list<string>|null,
 * }|false
 */
function torrent_select_one(mysqli $connection, array $settings, string $info_hash): array|false
{
    require_once __DIR__.'/../functions/torrent.normalize.meta.php';
    require_once __DIR__.'/db.fetch.once.php';

    $row = db_fetch_once(
        $connection,
        'SELECT `info_hash`, `user`, `name`, `size`, `listed`, `downloads`, '.
        '`filename`, `files`, `trackers`, `webseeds` '.
        'FROM `'.$settings['db_prefix'].'torrents` WHERE `info_hash`=?;',
        [$info_hash],
    );

    if ($row === false) {
        return false;
    }

    $meta = torrent_normalize_meta(
        is_string($row['filename']) ? $row['filename'] : null,
        is_string($row['files']) ? $row['files'] : null,
        is_string($row['trackers']) ? $row['trackers'] : null,
        is_string($row['webseeds']) ? $row['webseeds'] : null,
    );

    return [
        'info_hash' => (string) $row['info_hash'],
        'user' => is_string($row['user']) ? $row['user'] : null,
        'name' => is_string($row['name']) ? $row['name'] : null,
        'size' => (int) $row['size'],
        'listed' => (int) $row['listed'],
        'downloads' => (int) $row['downloads'],
        'filename' => $meta['filename'],
        'files' => $meta['files'],
        'trackers' => $meta['trackers'],
        'webseeds' => $meta['webseeds'],
    ];
}
