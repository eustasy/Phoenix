<?php

declare(strict_types=1);

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
