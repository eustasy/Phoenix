<?php

declare(strict_types=1);

////	view_stats_json

//	Returns tracker stats as a JSON-encoded string.
//	Input: $stats array with keys: peers, seeders, leechers, torrents, downloads, bandwidth.
//	       $settings array for phoenix_version.
//	Output: JSON string with a top-level 'tracker' object.
//
//	'version' is the bare version string, matching /api.

/**
 * @param array<string, int> $stats
 * @param PhoenixSettings $settings
 */
function view_stats_json(array $stats, array $settings): string
{
    return json_encode([
        'tracker' => [
            'version' => $settings['phoenix_version'],
            'release' => $settings['phoenix_release'],
            'peers' => $stats['peers'],
            'seeders' => $stats['seeders'],
            'leechers' => $stats['leechers'],
            'torrents' => $stats['torrents'],
            'downloads' => $stats['downloads'],
            'bandwidth' => $stats['bandwidth'],
        ],
    ]) ?: '';
}
