<?php

declare(strict_types=1);

////	torrent_parse_normalise_urls
// Shared URL cleanup: trim each entry, drop blanks, and drop duplicates while
// preserving first-seen order.

/**
 * @param list<string> $urls
 * @return list<string>
 */
function torrent_parse_normalise_urls(array $urls): array
{
    $out = [];
    foreach ($urls as $url) {
        $url = trim($url);
        if ($url === '') {
            continue;
        }
        if (in_array($url, $out, true)) {
            continue;
        }
        $out[] = $url;
    }

    return $out;
}
