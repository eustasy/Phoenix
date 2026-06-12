<?php

declare(strict_types=1);

////	view_index_json
// Renders a normalized $index array as a JSON-encoded string.
// Caller is responsible for emitting the Content-Type header.
//
// Arguments:
//   $index: array of torrents from torrents_select_listed(), each with keys:
//           info_hash, name, size, downloads, seeders, leechers, peers,
//           traffic, the four meta fields (filename, files, trackers,
//           webseeds), and the magnet link public/index.php builds.
//   $show_meta: when false (default) the four meta keys are omitted from
//               every row. When true they are passed through as-is. The
//               magnet link is included either way.
//
// Returns: JSON string.

/**
 * @param list<array{
 *     info_hash: string|null,
 *     name: string|null,
 *     size: int,
 *     downloads: int,
 *     seeders: int,
 *     leechers: int,
 *     peers: int,
 *     traffic: int,
 *     filename: string|null,
 *     files: list<array{path: string, length: int}>|null,
 *     trackers: list<string>|null,
 *     webseeds: list<string>|null,
 *     magnet?: string|null,
 * }> $index
 */
function view_index_json(array $index, bool $show_meta = false): string
{
    if ($show_meta) {
        return json_encode($index) ?: '';
    }

    $rows = [];
    foreach ($index as $torrent) {
        $rows[] = [
            'info_hash' => $torrent['info_hash'],
            'name' => $torrent['name'],
            'size' => $torrent['size'],
            'downloads' => $torrent['downloads'],
            'seeders' => $torrent['seeders'],
            'leechers' => $torrent['leechers'],
            'peers' => $torrent['peers'],
            'traffic' => $torrent['traffic'],
            'magnet' => $torrent['magnet'] ?? null,
        ];
    }

    return json_encode($rows) ?: '';
}
