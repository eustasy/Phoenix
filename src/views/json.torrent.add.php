<?php

declare(strict_types=1);

////	view_torrent_add_json

//	Returns the torrent added by the API as a JSON-encoded string.
//	Input: $torrent array with keys: user, info_hash, name, size, listed.
//	Output: JSON string with a top-level 'torrent' object.

/** @param array{user: string, info_hash: string, name: string|null, size: int, listed: int} $torrent */
function view_torrent_add_json(array $torrent): string
{
    return json_encode([
        'torrent' => [
            'user' => $torrent['user'],
            'info_hash' => $torrent['info_hash'],
            'name' => $torrent['name'],
            'size' => $torrent['size'],
            'listed' => $torrent['listed'],
        ],
    ]) ?: '';
}
