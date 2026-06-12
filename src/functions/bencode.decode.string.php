<?php

declare(strict_types=1);

////	bencode_decode_string
// Parses <len>:<bytes>. The length must be canonical digits (a leading zero is
// only allowed for the literal '0'), and the body must not run past end of
// input. On error, sets $offset to -1 (the bencode_decode failure sentinel).

function bencode_decode_string(string $data, int &$offset): string|false
{
    $colon = strpos($data, ':', $offset);
    if ($colon === false) {
        $offset = -1;

        return false;
    }

    $length_str = substr($data, $offset, $colon - $offset);
    if (! ctype_digit($length_str)) {
        $offset = -1;

        return false;
    }
    // Reject non-canonical leading zeros on the length prefix ('05:hello').
    if (strlen($length_str) > 1 && $length_str[0] === '0') {
        $offset = -1;

        return false;
    }

    $length = (int) $length_str;
    $start = $colon + 1;
    if ($start + $length > strlen($data)) {
        $offset = -1;

        return false;
    }

    $offset = $start + $length;

    return substr($data, $start, $length);
}
