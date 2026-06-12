<?php

declare(strict_types=1);

////	bencode_decode_integer
// Parses i<n>e. The body must match /^-?\d+$/ — this rejects 'ie' (empty),
// 'i-0e' (negative zero), leading zeros like 'i03e', and any non-digit content.
// On error, sets $offset to -1 (the bencode_decode failure sentinel).

function bencode_decode_integer(string $data, int &$offset): int|false
{
    require_once __DIR__.'/bencode.decode.is.canonical.int.php';

    $end = strpos($data, 'e', $offset);
    if ($end === false) {
        $offset = -1;

        return false;
    }

    $body = substr($data, $offset + 1, $end - $offset - 1);
    if (! bencode_decode_is_canonical_int($body)) {
        $offset = -1;

        return false;
    }

    $offset = $end + 1;

    return (int) $body;
}
