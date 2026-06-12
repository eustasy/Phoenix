<?php

declare(strict_types=1);

////	torrent_parse
// Parses the raw bytes of a .torrent file (BEP 3, plus BEP 12 announce-list and
// BEP 19 url-list web seeds) into a normalised array ready for torrent_add(). The
// input is attacker-supplied; the byte-size cap lives in the caller, here we just
// reject anything malformed by returning false.
//
// Decoding goes through bencode_decode(), which also hands back the exact raw byte
// slice of the 'info' dict so the info-hash is sha1() of those bytes (never a
// re-encode, which could reorder keys and corrupt the hash). The per-section
// extraction lives in the torrent.parse.* files, one function each.
//
// Returns false on malformed bencode, a missing/non-dict 'info', or no usable file
// data; otherwise:
//   [
//     'info_hash' => string,   // 40-char lowercase hex (sha1 of info_raw)
//     'name'      => string|null,
//     'filename'  => string|null,  // same as name (primary display name)
//     'size'      => int,
//     'files'     => list<array{path: string, length: int}>,
//     'trackers'  => list<string>,
//     'webseeds'  => list<string>,
//   ]

/**
 * @return array{
 *   info_hash: string,
 *   name: string|null,
 *   filename: string|null,
 *   size: int,
 *   files: list<array{path: string, length: int}>,
 *   trackers: list<string>,
 *   webseeds: list<string>,
 * }|false
 */
function torrent_parse(string $raw): array|false
{
    require_once __DIR__.'/bencode.decode.php';
    require_once __DIR__.'/torrent.parse.files.php';
    require_once __DIR__.'/torrent.parse.trackers.php';
    require_once __DIR__.'/torrent.parse.webseeds.php';

    $decoded = bencode_decode($raw);
    if ($decoded === false) {
        return false;
    }

    $root = $decoded['value'];
    $info_raw = $decoded['info_raw'];

    ////	Top-level dict with an 'info' dict
    // bencode_decode only sets info_raw for a top-level dict carrying 'info'; if
    // it is null the file is structurally not a torrent.
    if (! is_array($root) || $info_raw === null) {
        return false;
    }
    $info = $root['info'] ?? null;
    if (! is_array($info)) {
        return false;
    }

    $info_hash = sha1($info_raw);

    ////	Display name
    // BEP 23: prefer the explicitly-UTF-8 'name.utf-8' over 'name'.
    $name = null;
    if (isset($info['name.utf-8']) && is_string($info['name.utf-8'])) {
        $name = $info['name.utf-8'];
    } elseif (isset($info['name']) && is_string($info['name'])) {
        $name = $info['name'];
    }

    ////	Files and size
    // Multi-file torrents carry an 'files' list; single-file torrents carry a
    // top-level 'length'. The two are mutually exclusive in a valid torrent.
    if (isset($info['files']) && is_array($info['files'])) {
        $result = torrent_parse_files($info['files']);
        if ($result === false) {
            return false;
        }
        [$files, $size] = $result;
    } else {
        ////	Single-file
        if (! isset($info['length']) || ! is_int($info['length']) || $info['length'] < 0) {
            return false;
        }
        $size = $info['length'];
        $files = [[
            'path' => $name ?? '',
            'length' => $size,
        ]];
    }

    return [
        'info_hash' => $info_hash,
        'name' => $name,
        'filename' => $name,
        'size' => $size,
        'files' => $files,
        'trackers' => torrent_parse_trackers($root),
        'webseeds' => torrent_parse_webseeds($root),
    ];
}
