<?php

declare(strict_types=1);

////	view_torrent_add_json

//	Returns the torrent added by the API as a JSON-encoded string.
//	Input: $torrent array with keys: user, info_hash, name, size, listed, and
//	the four meta fields in normalized form (filename, files, trackers,
//	webseeds — each string|null or list|null). The meta keys are always present
//	in the object so consumers can rely on a stable shape; absent meta is null.
//	Output: JSON string with a top-level 'torrent' object.

/**
 * @param array{
 *     user: string,
 *     info_hash: string,
 *     name: string|null,
 *     size: int,
 *     listed: int,
 *     filename: string|null,
 *     files: list<array{path: string, length: int}>|null,
 *     trackers: list<string>|null,
 *     webseeds: list<string>|null,
 * } $torrent
 */
function view_torrent_add_json(array $torrent): string
{
    return json_encode([
        'torrent' => [
            'user' => $torrent['user'],
            'info_hash' => $torrent['info_hash'],
            'name' => $torrent['name'],
            'size' => $torrent['size'],
            'listed' => $torrent['listed'],
            'filename' => $torrent['filename'],
            'files' => $torrent['files'],
            'trackers' => $torrent['trackers'],
            'webseeds' => $torrent['webseeds'],
        ],
    ]) ?: '';
}
