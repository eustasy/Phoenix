<?php

declare(strict_types=1);

////	view_torrents_json
// Renders a collection of torrents (from torrents_select_all()) as a
// JSON-encoded string. Caller is responsible for emitting the Content-Type
// header.
//
// Input: $torrents — a list of rows in the torrents_select_all() shape (every
//        torrent, listed and unlisted, any owner, with swarm stats and the
//        four meta fields in normalized form).
// Output: JSON string with a top-level 'torrents' list; each row carries a
//         stable key set so consumers can rely on the shape.

/**
 * @param list<array{
 *     info_hash: string|null,
 *     user: string|null,
 *     name: string|null,
 *     size: int,
 *     listed: int,
 *     downloads: int,
 *     seeders: int,
 *     leechers: int,
 *     peers: int,
 *     traffic: int,
 *     filename: string|null,
 *     files: list<array{path: string, length: int}>|null,
 *     trackers: list<string>|null,
 *     webseeds: list<string>|null,
 * }> $torrents
 */
function view_torrents_json(array $torrents): string
{
    $rows = [];
    foreach ($torrents as $torrent) {
        $rows[] = [
            'info_hash' => $torrent['info_hash'],
            'user' => $torrent['user'],
            'name' => $torrent['name'],
            'size' => $torrent['size'],
            'listed' => $torrent['listed'],
            'downloads' => $torrent['downloads'],
            'seeders' => $torrent['seeders'],
            'leechers' => $torrent['leechers'],
            'peers' => $torrent['peers'],
            'traffic' => $torrent['traffic'],
            'filename' => $torrent['filename'],
            'files' => $torrent['files'],
            'trackers' => $torrent['trackers'],
            'webseeds' => $torrent['webseeds'],
        ];
    }

    return json_encode(['torrents' => $rows]) ?: '';
}
