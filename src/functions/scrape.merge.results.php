<?php

declare(strict_types=1);

////	scrape_merge_results
// Combines two mysqli_result sets (a peers GROUP BY info_hash query and a
// torrents-table query) into a single normalised $scrape array, keyed by
// info_hash. Pre-existing entries in $scrape (zero-initialised by
// once.scrape.torrent.php so that requested-but-unknown hashes still get a
// reply) are preserved and overwritten by query results when present.
//
// Each returned entry has integer seeders, leechers, peers (= seeders +
// leechers), size, downloads, and traffic (= size * downloads). intval()
// guards against NULL from SUM() over an empty group.
/**
 * @param array<string, array<string, mixed>> $scrape
 * @return array<string, array<string, mixed>>
 */
function scrape_merge_results(mysqli_result $peers, mysqli_result $torrents, array $scrape = []): array
{
    while ($row = mysqli_fetch_assoc($peers)) {
        $hash = (string) $row['info_hash'];
        $scrape[$hash]['seeders'] = $row['seeders'];
        $scrape[$hash]['leechers'] = $row['leechers'];
    }
    while ($row = mysqli_fetch_assoc($torrents)) {
        $hash = (string) $row['info_hash'];
        $scrape[$hash]['size'] = $row['size'] ?? 0;
        $scrape[$hash]['downloads'] = $row['downloads'];
    }
    foreach ($scrape as $hash => $entry) {
        $seeders = intval((string) ($entry['seeders'] ?? 0));
        $leechers = intval((string) ($entry['leechers'] ?? 0));
        $size = intval((string) ($entry['size'] ?? 0));
        $downloads = intval((string) ($entry['downloads'] ?? 0));
        $scrape[(string) $hash] = [
            'info_hash' => $hash,
            'seeders' => $seeders,
            'leechers' => $leechers,
            'peers' => $seeders + $leechers,
            'size' => $size,
            'downloads' => $downloads,
            'traffic' => $size * $downloads,
        ];
    }

    return $scrape;
}
