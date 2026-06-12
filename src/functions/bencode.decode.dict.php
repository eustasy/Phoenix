<?php

declare(strict_types=1);

////	bencode_decode_dict
// Parses d...e into an associative array. Keys must be byte strings and the
// container must be terminated. Only the top-level dict (depth 1) captures the
// raw byte slice of its 'info' value into $info_raw — that is the slice
// torrent_parse hashes for the info-hash. Restricting capture to depth 1 means a
// nested 'info' key inside some other value can never overwrite (and corrupt)
// the real top-level info slice. On error, $offset carries the -1 sentinel.

/**
 * @param string $data
 * @param int $offset
 * @param int $depth
 * @param string|null $info_raw
 * @return array<string, mixed>|false
 */
function bencode_decode_dict(string $data, int &$offset, int $depth, ?string &$info_raw): array|false
{
    require_once __DIR__.'/bencode.decode.string.php';
    require_once __DIR__.'/bencode.decode.value.php';

    $length = strlen($data);
    $offset++; // skip 'd'
    $out = [];

    while (true) {
        if ($offset >= $length) {
            $offset = -1;

            return false;
        }
        if ($data[$offset] === 'e') {
            $offset++; // skip 'e'

            return $out;
        }

        ////	Key
        // Dictionary keys are always byte strings.
        if (! ctype_digit($data[$offset])) {
            $offset = -1;

            return false;
        }
        $key = bencode_decode_string($data, $offset);
        if ($offset === -1 || ! is_string($key)) {
            $offset = -1;

            return false;
        }

        ////	Value
        // Record the byte offsets bracketing the top-level 'info' value so its
        // exact raw slice can be hashed without re-encoding.
        $value_start = $offset;
        $item = bencode_decode_value($data, $offset, $depth + 1, $info_raw);
        if ($offset === -1) {
            return false;
        }
        if ($depth === 1 && $key === 'info') {
            $info_raw = substr($data, $value_start, $offset - $value_start);
        }

        $out[$key] = $item;
    }
}
