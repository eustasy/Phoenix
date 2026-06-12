<?php

declare(strict_types=1);

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
