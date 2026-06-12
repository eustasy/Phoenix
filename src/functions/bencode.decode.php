<?php

declare(strict_types=1);

////	bencode_decode
// Decodes a bencoded byte string (BEP 3) back into a PHP value — the inverse of
// bencode_encode(). The input is attacker-supplied (an uploaded .torrent), so the
// decoder is deliberately strict: it rejects trailing bytes, unterminated
// containers, malformed lengths/integers, and runaway nesting depth. The byte-size
// cap on the whole input is the caller's job; here we only bound recursion.
//
// The grammar lives in one worker per token type (bencode.decode.value.php and
// the bencode.decode.* files it pulls in), one function per file.
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

/**
 * @return array{value: mixed, info_raw: string|null}|false
 */
function bencode_decode(string $data): array|false
{
    require_once __DIR__.'/bencode.decode.value.php';

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
