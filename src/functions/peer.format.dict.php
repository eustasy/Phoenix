<?php

declare(strict_types=1);

////	peer_format_dict
// Builds the BEP 3 non-compact announce entry for a single peer row as a PHP
// array ready for bencode_encode(). IPv4 is preferred when a row carries both
// families. $include_peer_id should be the negation of $peer['no_peer_id'] —
// peers that explicitly request omission pass false.
//
// Returns null when the row has neither an IPv4 nor an IPv6 address, so the
// caller can skip it rather than emit an empty peer dict.
//
// Key order in the returned array is irrelevant: bencode_encode() sorts dict
// keys into the raw byte order BEP 3 requires ('ip' < 'peer id' < 'port').
// Ports are cast to int so they bencode as integers, not strings — mysqli
// hands back numeric columns as strings.
/**
 * @param array<string, float|int|string|null> $row
 * @return array<string, mixed>|null
 */
function peer_format_dict(array $row, bool $include_peer_id): ?array
{
    if ($row['ipv4'] != null) {
        $ip = (string) $row['ipv4'];
        $port = (int) $row['portv4'];
    } elseif ($row['ipv6'] != null) {
        $ip = (string) $row['ipv6'];
        $port = (int) $row['portv6'];
    } else {
        return null;
    }

    $dict = ['ip' => $ip, 'port' => $port];
    if ($include_peer_id) {
        $dict['peer id'] = hex2bin((string) $row['peer_id']);
    }

    return $dict;
}
