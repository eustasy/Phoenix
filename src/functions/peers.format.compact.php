<?php

declare(strict_types=1);

////	peers_format_compact
// Concatenates the BEP 23 (IPv4, 6 bytes) and BEP 7 (IPv6, 18 bytes) compact
// peer-info bytes for a list of peer rows. Stored as hex in the database, so
// each row's compactv4 / compactv6 is hex2bin'd back to raw binary. Rows
// whose hex blob is empty are skipped for that family. Returns
// array{v4: string, v6: string} of raw binary (NOT bencoded — the caller
// handles the length prefix).
/**
 * @param array<int, array<string, float|int|string|null>> $rows
 * @return array{v4: string, v6: string}
 */
function peers_format_compact(array $rows): array
{
    $v4 = '';
    $v6 = '';
    foreach ($rows as $row) {
        if ($row['compactv4'] !== null && $row['compactv4'] !== '') {
            $v4 .= hex2bin((string) $row['compactv4']);
        }
        if ($row['compactv6'] !== null && $row['compactv6'] !== '') {
            $v6 .= hex2bin((string) $row['compactv6']);
        }
    }

    return ['v4' => $v4, 'v6' => $v6];
}
