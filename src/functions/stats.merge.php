<?php

declare(strict_types=1);

////	stats_merge
// Merge peer counts and download totals into a single stats array.
// Returns array with all stats as integers, or false if either input is false.

/**
 * @param array<string, mixed>|false $peer_counts
 * @param array<string, mixed>|false $download_totals
 * @return array<string, int>|false
 */
function stats_merge(array|false $peer_counts, array|false $download_totals): array|false
{
    if (! $peer_counts || ! $download_totals) {
        return false;
    }

    $stats = [];
    $stats['seeders'] = intval((string) $peer_counts['seeders']);
    $stats['leechers'] = intval((string) $peer_counts['leechers']);
    $stats['torrents'] = intval((string) $peer_counts['torrents']);
    $stats['downloads'] = intval((string) $download_totals['downloads']);
    $stats['traffic'] = intval((string) $download_totals['traffic']);
    $stats['peers'] = $stats['seeders'] + $stats['leechers'];

    return $stats;
}
