<?php

declare(strict_types=1);

////	torrent_parse_webseeds
// Normalises the BEP 19 'url-list' web seeds. It may be a single byte string or
// a list of byte strings; either way the result is a trimmed, de-duplicated list
// with blanks dropped.

/**
 * @param array<string, mixed> $root
 * @return list<string>
 */
function torrent_parse_webseeds(array $root): array
{
    require_once __DIR__.'/torrent.parse.normalise.urls.php';

    if (! isset($root['url-list'])) {
        return [];
    }

    $raw = $root['url-list'];
    $urls = [];
    if (is_string($raw)) {
        $urls[] = $raw;
    } elseif (is_array($raw)) {
        foreach ($raw as $url) {
            if (is_string($url)) {
                $urls[] = $url;
            }
        }
    }

    return torrent_parse_normalise_urls($urls);
}
