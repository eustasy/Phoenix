<?php

declare(strict_types=1);

////	bencode_decode_value
// Recursive worker for bencode_decode(). Reads one bencoded value starting at
// $offset, advances $offset past it on success, and returns the decoded value.
// On any error it sets $offset to -1 (the failure sentinel) and returns false —
// callers must check $offset, since false is also a legitimate "this branch
// failed" marker and bencode itself has no false value to confuse it with.
//
// $info_raw is threaded by reference so the single top-level 'info' dict can be
// captured by raw byte offset as it is parsed.

const BENCODE_DECODE_MAX_DEPTH = 64;

/**
 * @param string $data
 * @param int $offset
 * @param int $depth
 * @param string|null $info_raw
 * @return mixed
 */
function bencode_decode_value(string $data, int &$offset, int $depth, ?string &$info_raw): mixed
{
    require_once __DIR__.'/bencode.decode.integer.php';
    require_once __DIR__.'/bencode.decode.string.php';
    require_once __DIR__.'/bencode.decode.list.php';
    require_once __DIR__.'/bencode.decode.dict.php';

    if ($depth > BENCODE_DECODE_MAX_DEPTH) {
        $offset = -1;

        return false;
    }

    $length = strlen($data);
    if ($offset >= $length) {
        $offset = -1;

        return false;
    }

    $char = $data[$offset];

    ////	Integer
    if ($char === 'i') {
        return bencode_decode_integer($data, $offset);
    }

    ////	List
    if ($char === 'l') {
        return bencode_decode_list($data, $offset, $depth, $info_raw);
    }

    ////	Dictionary
    if ($char === 'd') {
        return bencode_decode_dict($data, $offset, $depth, $info_raw);
    }

    ////	String
    // Anything else must be the start of a <len>:<bytes> byte string.
    if (ctype_digit($char)) {
        return bencode_decode_string($data, $offset);
    }

    $offset = -1;

    return false;
}
