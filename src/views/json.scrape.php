<?php

declare(strict_types=1);

////	view_scrape_json

//	Returns scrape results as a JSON-encoded string.
//	Input: $scrape array of torrent arrays, each with keys:
//	       info_hash, seeders, leechers, peers, size, downloads, bandwidth.
//	Output: JSON string with torrents indexed by info_hash.

/**
 * @param array<string, array{info_hash: string, seeders: int, leechers: int, peers: int, size: int, downloads: int, bandwidth: int}> $scrape
 * @param int $min_request_interval BEP 48 scrape-throttle hint (seconds); 0 omits it
 */
function view_scrape_json(array $scrape, int $min_request_interval = 0): string
{
    $json = [];
    foreach ($scrape as $torrent) {
        $json[$torrent['info_hash']] = [
            'info_hash' => $torrent['info_hash'],
            'seeders' => $torrent['seeders'],
            'leechers' => $torrent['leechers'],
            'peers' => $torrent['peers'],
            'size' => $torrent['size'],
            'downloads' => $torrent['downloads'],
            'bandwidth' => $torrent['bandwidth'],
        ];
    }

    // BEP 48's min_request_interval (parity with the bencode `flags` dict). A
    // 40-hex info_hash can never collide with this key.
    if ($min_request_interval > 0) {
        $json['min_request_interval'] = $min_request_interval;
    }

    return json_encode($json) ?: '';
}
