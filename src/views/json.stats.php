<?php

declare(strict_types=1);

////	view_stats_json

//	Returns tracker stats as a JSON-encoded string.
//	Input: $stats array with keys: peers, seeders, leechers, torrents, downloads, traffic.
//	       $settings array for phoenix_version.
//	Output: JSON string with a top-level 'tracker' object.
//
//	'version' is the bare version string, matching /api. It carried a
//	'$Id: … $,' wrapper until v4.3: a Subversion keyword inherited from
//	PeerTracker, which git never expanded, plus a trailing comma left behind
//	when the response stopped being concatenated by hand.

/**
 * @param array<string, int> $stats
 * @param PhoenixSettings $settings
 */
function view_stats_json(array $stats, array $settings): string
{
    return json_encode([
        'tracker' => [
            'version' => $settings['phoenix_version'],
            'peers' => $stats['peers'],
            'seeders' => $stats['seeders'],
            'leechers' => $stats['leechers'],
            'torrents' => $stats['torrents'],
            'downloads' => $stats['downloads'],
            'traffic' => $stats['traffic'],
        ],
    ]) ?: '';
}
