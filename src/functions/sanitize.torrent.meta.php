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
// Per-field rules live in the sanitize.torrent.meta.* files, one function each.

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
    require_once __DIR__.'/sanitize.torrent.meta.filename.php';
    require_once __DIR__.'/sanitize.torrent.meta.files.php';
    require_once __DIR__.'/sanitize.torrent.meta.urls.php';

    return [
        'filename' => sanitize_torrent_meta_filename($input['filename'] ?? null),
        'files' => sanitize_torrent_meta_files($input['files'] ?? null),
        'trackers' => sanitize_torrent_meta_urls($input['trackers'] ?? null),
        'webseeds' => sanitize_torrent_meta_urls($input['webseeds'] ?? null),
    ];
}
