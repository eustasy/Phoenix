<?php

declare(strict_types=1);

////	view_scrape_json

//	Returns scrape results as a JSON-encoded string.
//	Input: $scrape array of torrent arrays, each with keys:
//	       info_hash, seeders, leechers, peers, size, downloads, traffic.
//	Output: JSON string with torrents indexed by info_hash.

/** @param array<string, array<string, mixed>> $scrape */
function view_scrape_json(array $scrape): string
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
            'traffic' => $torrent['traffic'],
        ];
    }

    return json_encode($json) ?: '';
}
