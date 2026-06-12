<?php

declare(strict_types=1);

////	sanitize_torrent_meta
// Validates the four optional meta fields from a raw request ($_POST/$_GET
// merge, or parsed-torrent values cast to their request shape) and returns
// BOTH the normalized form (for views/API output) and the storage form (the
// exact string written to the DB column), per field. A field that is absent,
// empty, or fully invalid yields null for both forms.
//
// The two forms are produced from a single validation pass so the round-trip is
// guaranteed consistent: whatever the views render is exactly what the column
// holds, decoded back. Storage forms mirror torrent_normalize_meta's expected
// input — JSON text for `files`, newline-joined URLs for `trackers`/`webseeds`.
//
// Field rules:
//   filename — trimmed string, capped to the varchar(255) column; '' -> null.
//   files    — a JSON string (or already-decoded list) that must reduce to a
//              non-empty list of {path: string, length: int >= 0}; malformed
//              elements are dropped, and nothing valid -> null.
//   trackers — newline-delimited URLs: split, trim, drop blanks and anything
//   webseeds   failing FILTER_VALIDATE_URL, dedupe preserving order; none -> null.

/**
 * @param array<string, mixed> $input raw request values keyed by field name
 * @return array{
 *     filename: array{normalized: string|null, storage: string|null},
 *     files: array{normalized: list<array{path: string, length: int}>|null, storage: string|null},
 *     trackers: array{normalized: list<string>|null, storage: string|null},
 *     webseeds: array{normalized: list<string>|null, storage: string|null},
 * }
 */
function sanitize_torrent_meta(array $input): array
{
    return [
        'filename' => sanitize_torrent_meta_filename($input['filename'] ?? null),
        'files' => sanitize_torrent_meta_files($input['files'] ?? null),
        'trackers' => sanitize_torrent_meta_urls($input['trackers'] ?? null),
        'webseeds' => sanitize_torrent_meta_urls($input['webseeds'] ?? null),
    ];
}

////	sanitize_torrent_meta_filename
// Trim, cap to 255 bytes, and collapse the empty string to null. The same
// string is both the normalized and storage form.

/** @return array{normalized: string|null, storage: string|null} */
function sanitize_torrent_meta_filename(mixed $value): array
{
    if (! is_string($value)) {
        return ['normalized' => null, 'storage' => null];
    }

    $filename = substr(trim($value), 0, 255);
    if ($filename === '') {
        return ['normalized' => null, 'storage' => null];
    }

    return ['normalized' => $filename, 'storage' => $filename];
}

////	sanitize_torrent_meta_files
// Accept either a JSON string (the API/form shape) or an already-decoded list
// (the torrent_parse() shape) and reduce it to a clean list of
// {path: string, length: int >= 0}. Malformed elements are dropped; an empty
// result is null. Storage is json_encode of the cleaned list.

/** @return array{normalized: list<array{path: string, length: int}>|null, storage: string|null} */
function sanitize_torrent_meta_files(mixed $value): array
{
    $null = ['normalized' => null, 'storage' => null];

    if (is_string($value)) {
        if (trim($value) === '') {
            return $null;
        }
        $decoded = json_decode($value, true);
    } elseif (is_array($value)) {
        $decoded = $value;
    } else {
        return $null;
    }

    if (! is_array($decoded) || ! array_is_list($decoded)) {
        return $null;
    }

    $clean = [];
    foreach ($decoded as $entry) {
        if (
            ! is_array($entry) ||
            ! isset($entry['path']) ||
            ! is_string($entry['path']) ||
            ! isset($entry['length']) ||
            ! is_int($entry['length']) ||
            $entry['length'] < 0
        ) {
            continue;
        }
        $clean[] = [
            'path' => $entry['path'],
            'length' => $entry['length'],
        ];
    }

    if ($clean === []) {
        return $null;
    }

    $storage = json_encode($clean);

    return [
        'normalized' => $clean,
        'storage' => $storage === false ? null : $storage,
    ];
}

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
