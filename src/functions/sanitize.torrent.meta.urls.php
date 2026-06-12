<?php

declare(strict_types=1);

////	sanitize_torrent_meta_urls
// Accept either a newline-delimited string (the API/form shape) or an
// already-split list (the torrent_parse() shape). Trim each entry, drop blanks
// and anything FILTER_VALIDATE_URL rejects, then dedupe preserving first-seen
// order. An empty result is null; storage is the surviving URLs implode("\n")'d.

/** @return array{normalized: list<string>|null, storage: string|null} */
function sanitize_torrent_meta_urls(mixed $value): array
{
    if (is_string($value)) {
        $lines = explode("\n", $value);
    } elseif (is_array($value)) {
        $lines = $value;
    } else {
        return ['normalized' => null, 'storage' => null];
    }

    $urls = [];
    foreach ($lines as $line) {
        if (! is_string($line)) {
            continue;
        }
        $line = trim($line);
        if ($line === '' || filter_var($line, FILTER_VALIDATE_URL) === false) {
            continue;
        }
        if (in_array($line, $urls, true)) {
            continue;
        }
        $urls[] = $line;
    }

    if ($urls === []) {
        return ['normalized' => null, 'storage' => null];
    }

    return ['normalized' => $urls, 'storage' => implode("\n", $urls)];
}
