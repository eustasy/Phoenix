<?php

declare(strict_types=1);

////	torrent_normalize_meta
// Converts raw nullable DB columns for the four meta fields into a
// normalized shape suitable for views and API consumers.
//
// - filename: returned as-is (string or null)
// - files: JSON-decoded list; null when the column is NULL, the JSON is
//   invalid, or the top-level value is not a list. Each element that lacks
//   a string `path` or integer-compatible `length` is silently dropped.
//   `length` is always cast to int; `path` always to string.
// - trackers: newline-split, trimmed, blanks removed; null when column is NULL
// - webseeds: same as trackers

/**
 * @return array{
 *     filename: string|null,
 *     files: list<array{path: string, length: int}>|null,
 *     trackers: list<string>|null,
 *     webseeds: list<string>|null,
 * }
 */
function torrent_normalize_meta(
    ?string $filename,
    ?string $files,
    ?string $trackers,
    ?string $webseeds,
): array {
    ////	files
    // Decode JSON column; null on NULL input, invalid JSON, or non-list top-level.
    $filesNormalized = null;
    if ($files !== null) {
        $decoded = json_decode($files, true);
        if (is_array($decoded) && array_is_list($decoded)) {
            $filesNormalized = [];
            foreach ($decoded as $entry) {
                if (
                    is_array($entry) &&
                    isset($entry['path']) &&
                    isset($entry['length']) &&
                    is_numeric($entry['length'])
                ) {
                    $filesNormalized[] = [
                        'path' => (string) $entry['path'],
                        'length' => (int) $entry['length'],
                    ];
                }
            }
        }
    }

    ////	trackers / webseeds
    // Split on newlines, trim whitespace, drop blank lines.
    $splitLines = static function (?string $value): ?array {
        if ($value === null) {
            return null;
        }

        $lines = array_filter(
            array_map('trim', explode("\n", $value)),
            static fn (string $line): bool => $line !== '',
        );

        return array_values($lines);
    };

    return [
        'filename' => $filename,
        'files' => $filesNormalized,
        'trackers' => $splitLines($trackers),
        'webseeds' => $splitLines($webseeds),
    ];
}
