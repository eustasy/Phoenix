<?php

declare(strict_types=1);

////	format_bytes
// Render a byte count as a short human-readable size (binary units, labelled
// B/KB/MB/GB/TB/PB). One decimal place for small magnitudes (< 10) above the
// byte unit so "4.8 GB" and "1.0 GB" read naturally, none otherwise ("670 MB").
// Used for display only — raw byte counts are kept alongside where precision
// matters. Negative inputs are clamped to zero.

function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return max(0, $bytes).' B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $power = (int) min(floor(log($bytes, 1024)), count($units) - 1);
    $value = $bytes / (1024 ** $power);
    $decimals = $value < 10 ? 1 : 0;

    return number_format($value, $decimals).' '.$units[$power];
}
