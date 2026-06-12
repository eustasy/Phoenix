<?php

declare(strict_types=1);

////	torrent_parse_trackers
// Flattens announce sources into an ordered, de-duplicated list: the single
// 'announce' string first, then every URL in 'announce-list' (BEP 12: a list of
// lists of strings). URLs are trimmed; blanks and duplicates are dropped.

/**
 * @param array<string, mixed> $root
 * @return list<string>
 */
function torrent_parse_trackers(array $root): array
{
    require_once __DIR__.'/torrent.parse.normalise.urls.php';

    $urls = [];

    if (isset($root['announce']) && is_string($root['announce'])) {
        $urls[] = $root['announce'];
    }

    if (isset($root['announce-list']) && is_array($root['announce-list'])) {
        foreach ($root['announce-list'] as $tier) {
            if (! is_array($tier)) {
                continue;
            }
            foreach ($tier as $url) {
                if (is_string($url)) {
                    $urls[] = $url;
                }
            }
        }
    }

    return torrent_parse_normalise_urls($urls);
}
