<?php

declare(strict_types=1);

////	view_scrape_bencode
// Renders a normalized $scrape array as a bencode scrape response (BEP 48).
// The files-dict key is the raw 20-byte info_hash (hex2bin), not hex.
// BEP 48 uses 'complete' (seeders), 'downloaded' (finished downloads),
// 'incomplete' (leechers).
//
// Arguments:
//   $scrape: array of torrents indexed by info_hash (40-char hex), each with:
//            - info_hash: string (40-char hex)
//            - seeders: int
//            - leechers: int
//            - downloads: int
//
// The files dict is built as a PHP array keyed by raw binary info_hash and
// cast to (object) so bencode_encode() emits it as a dict even when empty
// (no torrents -> 'de', not an empty list). The encoder sorts the hashes and
// each stats dict into the byte order BEP 48 wants.
//
// Returns: bencoded scrape response string.
/** @param array<string, array{info_hash: string, seeders: int, leechers: int, peers: int, size: int, downloads: int, traffic: int}> $scrape */
function view_scrape_bencode(array $scrape): string
{
    require_once __DIR__.'/../functions/bencode.encode.php';

    $files = [];
    foreach ($scrape as $torrent) {
        $files[hex2bin($torrent['info_hash'])] = [
            'complete' => $torrent['seeders'],
            'downloaded' => $torrent['downloads'],
            'incomplete' => $torrent['leechers'],
        ];
    }

    return bencode_encode(['files' => (object) $files]);
}
