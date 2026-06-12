<?php

declare(strict_types=1);

////	bencode_decode
// Decodes a bencoded byte string (BEP 3) back into a PHP value — the inverse of
// bencode_encode(). The input is attacker-supplied (an uploaded .torrent), so the
// decoder is deliberately strict: it rejects trailing bytes, unterminated
// containers, malformed lengths/integers, and runaway nesting depth. The byte-size
// cap on the whole input is the caller's job; here we only bound recursion.
//
// Type mapping (inverse of the encoder):
//   i<n>e        -> int
//   <len>:<bytes> -> string (binary-safe)
//   l...e        -> list array
//   d...e        -> associative array (byte-string keys)
// An empty list and an empty dict both decode to [] — bencode decoding is
// one-way, so this lossy collapse is acceptable for our use.
//
// Returns ['value' => mixed, 'info_raw' => string|null]:
//   * 'value'    — the decoded top-level value.
//   * 'info_raw' — the EXACT raw byte slice of the top-level dict's 'info' value,
//                  captured by offset (never re-encoded, so sha1($info_raw) yields
//                  the torrent's info-hash), or null when the top-level value is not
//                  a dict or carries no 'info' key.
// Returns false on any malformed input.

const BENCODE_DECODE_MAX_DEPTH = 64;

/**
 * @return array{value: mixed, info_raw: string|null}|false
 */
function bencode_decode(string $data): array|false
{
    if ($data === '') {
        return false;
    }

    $offset = 0;
    $info_raw = null;

    $value = bencode_decode_value($data, $offset, 1, $info_raw);
    if ($value === false && $offset === -1) {
        // Sentinel: bencode_decode_value flags failure by setting $offset to -1.
        return false;
    }

    ////	No trailing bytes
    // The top-level value must consume the entire input; anything left over is
    // malformed (a torrent file is a single bencoded dict, nothing more).
    if ($offset !== strlen($data)) {
        return false;
    }

    return ['value' => $value, 'info_raw' => $info_raw];
}

////	bencode_decode_value
// Recursive worker. Reads one bencoded value starting at $offset, advances
// $offset past it on success, and returns the decoded value. On any error it
// sets $offset to -1 (the failure sentinel) and returns false — callers must
// check $offset, since false is also a legitimate "this branch failed" marker
// and bencode itself has no false value to confuse it with.
//
// $info_raw is threaded by reference so the single top-level 'info' dict can be
// captured by raw byte offset as it is parsed.

/**
 * @param string $data
 * @param int $offset
 * @param int $depth
 * @param string|null $info_raw
 * @return mixed
 */
function bencode_decode_value(string $data, int &$offset, int $depth, ?string &$info_raw): mixed
{
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

////	bencode_decode_integer
// Parses i<n>e. The body must match /^-?\d+$/ — this rejects 'ie' (empty),
// 'i-0e' (negative zero), leading zeros like 'i03e', and any non-digit content.

function bencode_decode_integer(string $data, int &$offset): int|false
{
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

////	bencode_decode_is_canonical_int
// True only for a canonical bencode integer body: optional single leading '-',
// then digits, no leading zeros (except the single digit '0'), and '-0' rejected.

function bencode_decode_is_canonical_int(string $body): bool
{
    if ($body === '') {
        return false;
    }

    $digits = $body;
    $negative = false;
    if ($body[0] === '-') {
        $negative = true;
        $digits = substr($body, 1);
    }

    if ($digits === '' || ! ctype_digit($digits)) {
        return false;
    }

    // Reject leading zeros ('03', '00') and negative zero ('-0').
    if (strlen($digits) > 1 && $digits[0] === '0') {
        return false;
    }
    if ($negative && $digits === '0') {
        return false;
    }

    return true;
}

////	bencode_decode_string
// Parses <len>:<bytes>. The length must be canonical digits (a leading zero is
// only allowed for the literal '0'), and the body must not run past end of input.

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

////	bencode_decode_list
// Parses l...e into a sequential array. Each element is decoded recursively;
// an unterminated list (no closing 'e' before end of input) is malformed.

/**
 * @param string $data
 * @param int $offset
 * @param int $depth
 * @param string|null $info_raw
 * @return list<mixed>|false
 */
function bencode_decode_list(string $data, int &$offset, int $depth, ?string &$info_raw): array|false
{
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

////	bencode_decode_dict
// Parses d...e into an associative array. Keys must be byte strings and the
// container must be terminated. Only the top-level dict (depth 1) captures the
// raw byte slice of its 'info' value into $info_raw — that is the slice
// torrent_parse hashes for the info-hash. Restricting capture to depth 1 means a
// nested 'info' key inside some other value can never overwrite (and corrupt)
// the real top-level info slice.

/**
 * @param string $data
 * @param int $offset
 * @param int $depth
 * @param string|null $info_raw
 * @return array<string, mixed>|false
 */
function bencode_decode_dict(string $data, int &$offset, int $depth, ?string &$info_raw): array|false
{
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
