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
// re-encode, which could reorder keys and corrupt the hash).
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

////	torrent_parse_files
// Builds the multi-file 'files' list and total size. Each element joins its
// 'path' components with '/' (preferring 'path.utf-8' per BEP 23) and carries a
// non-negative integer 'length'. Malformed elements are skipped, not fatal —
// but if no valid element survives the torrent is rejected.

/**
 * @param array<mixed> $entries
 * @return array{0: list<array{path: string, length: int}>, 1: int}|false
 */
function torrent_parse_files(array $entries): array|false
{
    $files = [];
    $size = 0;

    foreach ($entries as $entry) {
        if (! is_array($entry)) {
            continue;
        }

        ////	Length
        if (! isset($entry['length']) || ! is_int($entry['length']) || $entry['length'] < 0) {
            continue;
        }
        $length = $entry['length'];

        ////	Path
        // Prefer the UTF-8 path; both are lists of byte-string components.
        $parts = null;
        if (isset($entry['path.utf-8']) && is_array($entry['path.utf-8'])) {
            $parts = $entry['path.utf-8'];
        } elseif (isset($entry['path']) && is_array($entry['path'])) {
            $parts = $entry['path'];
        }
        if ($parts === null || $parts === []) {
            continue;
        }

        $clean = [];
        $valid = true;
        foreach ($parts as $part) {
            if (! is_string($part)) {
                $valid = false;
                break;
            }
            $clean[] = $part;
        }
        if (! $valid) {
            continue;
        }

        $files[] = [
            'path' => implode('/', $clean),
            'length' => $length,
        ];
        $size += $length;
    }

    if ($files === []) {
        return false;
    }

    return [$files, $size];
}

////	torrent_parse_trackers
// Flattens announce sources into an ordered, de-duplicated list: the single
// 'announce' string first, then every URL in 'announce-list' (BEP 12: a list of
// lists of strings). URLs are trimmed; blanks and duplicates are dropped.

/**
 * @param array<string, mixed> $root
 * @return list<string>
 */
function torrent_parse_trackers(array $root): array
{
    $urls = [];

    if (isset($root['announce']) && is_string($root['announce'])) {
        $urls[] = $root['announce'];
    }

    if (isset($root['announce-list']) && is_array($root['announce-list'])) {
        foreach ($root['announce-list'] as $tier) {
            if (! is_array($tier)) {
                continue;
            }
            foreach ($tier as $url) {
                if (is_string($url)) {
                    $urls[] = $url;
                }
            }
        }
    }

    return torrent_parse_normalise_urls($urls);
}

////	torrent_parse_webseeds
// Normalises the BEP 19 'url-list' web seeds. It may be a single byte string or
// a list of byte strings; either way the result is a trimmed, de-duplicated list
// with blanks dropped.

/**
 * @param array<string, mixed> $root
 * @return list<string>
 */
function torrent_parse_webseeds(array $root): array
{
    if (! isset($root['url-list'])) {
        return [];
    }

    $raw = $root['url-list'];
    $urls = [];
    if (is_string($raw)) {
        $urls[] = $raw;
    } elseif (is_array($raw)) {
        foreach ($raw as $url) {
            if (is_string($url)) {
                $urls[] = $url;
            }
        }
    }

    return torrent_parse_normalise_urls($urls);
}

////	torrent_parse_normalise_urls
// Shared URL cleanup: trim each entry, drop blanks, and drop duplicates while
// preserving first-seen order.

/**
 * @param list<string> $urls
 * @return list<string>
 */
function torrent_parse_normalise_urls(array $urls): array
{
    $out = [];
    foreach ($urls as $url) {
        $url = trim($url);
        if ($url === '') {
            continue;
        }
        if (in_array($url, $out, true)) {
            continue;
        }
        $out[] = $url;
    }

    return $out;
}
