<?php

declare(strict_types=1);

////	bencode_decode_list
// Parses l...e into a sequential array. Each element is decoded recursively
// via bencode_decode_value(); an unterminated list (no closing 'e' before end
// of input) is malformed. On error, $offset carries the -1 failure sentinel.

/**
 * @param string $data
 * @param int $offset
 * @param int $depth
 * @param string|null $info_raw
 * @return list<mixed>|false
 */
function bencode_decode_list(string $data, int &$offset, int $depth, ?string &$info_raw): array|false
{
    require_once __DIR__.'/bencode.decode.value.php';

    $length = strlen($data);
    $offset++; // skip 'l'
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

        $item = bencode_decode_value($data, $offset, $depth + 1, $info_raw);
        if ($offset === -1) {
            return false;
        }
        $out[] = $item;
    }
}
