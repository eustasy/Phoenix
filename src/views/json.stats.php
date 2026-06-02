<?php

declare(strict_types=1);

////	view_stats_json

//	Returns tracker stats as a JSON-encoded string.
//	Input: $stats array with keys: peers, seeders, leechers, torrents, downloads, traffic.
//	       $settings array for phoenix_version.
//	Output: JSON string with a top-level 'tracker' object.

function view_stats_json($stats, $settings)
{
    return json_encode([
        'tracker' => [
            'version' => '$Id: '.$settings['phoenix_version'].' $,',
            'peers' => $stats['peers'],
            'seeders' => $stats['seeders'],
            'leechers' => $stats['leechers'],
            'torrents' => $stats['torrents'],
            'downloads' => $stats['downloads'],
            'traffic' => $stats['traffic'],
        ],
    ]);
}
