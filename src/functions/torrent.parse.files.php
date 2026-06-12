<?php

declare(strict_types=1);

////	torrent_parse_files
// Builds the multi-file 'files' list and total size. Each element joins its
// 'path' components with '/' (preferring 'path.utf-8' per BEP 23) and carries a
// non-negative integer 'length'. Malformed elements are skipped, not fatal —
// but if no valid element survives the torrent is rejected.

/**
 * @param array<mixed> $entries
 * @return array{0: list<array{path: string, length: int}>, 1: int}|false
 */
function torrent_parse_files(array $entries): array|false
{
    $files = [];
    $size = 0;

    foreach ($entries as $entry) {
        if (! is_array($entry)) {
            continue;
        }

        ////	Length
        if (! isset($entry['length']) || ! is_int($entry['length']) || $entry['length'] < 0) {
            continue;
        }
        $length = $entry['length'];

        ////	Path
        // Prefer the UTF-8 path; both are lists of byte-string components.
        $parts = null;
        if (isset($entry['path.utf-8']) && is_array($entry['path.utf-8'])) {
            $parts = $entry['path.utf-8'];
        } elseif (isset($entry['path']) && is_array($entry['path'])) {
            $parts = $entry['path'];
        }
        if ($parts === null || $parts === []) {
            continue;
        }

        $clean = [];
        $valid = true;
        foreach ($parts as $part) {
            if (! is_string($part)) {
                $valid = false;
                break;
            }
            $clean[] = $part;
        }
        if (! $valid) {
            continue;
        }

        $files[] = [
            'path' => implode('/', $clean),
            'length' => $length,
        ];
        $size += $length;
    }

    if ($files === []) {
        return false;
    }

    return [$files, $size];
}
