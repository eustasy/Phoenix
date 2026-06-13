<?php

declare(strict_types=1);

////	view_torrent_json

//	Returns one torrent as a JSON-encoded string. Shared by the API's add,
//	list, delist, and delete responses (delete renders the row as it was
//	removed).
//	Input: $torrent array with keys: user (string|null — null for an admin
//	acting on an unowned torrent), info_hash, name, size, listed, and the four
//	meta fields in normalized form (filename, files, trackers, webseeds — each
//	string|null or list|null). The meta keys are always present in the object so
//	consumers can rely on a stable shape; absent meta is null.
//	Output: JSON string with a top-level 'torrent' object.

/**
 * @param array{
 *     user: string|null,
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
function view_torrent_json(array $torrent): string
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
