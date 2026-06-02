<?php

declare(strict_types=1);

////	bencode_encode
// Serialises a PHP value to a bencoded string (BEP 3). This is the single
// emitter every bencode view is built on, so the fiddly, regression-prone
// details — length prefixes that match the byte body, dict keys in raw byte
// order, balanced container tokens — live here once instead of in each view.
//
// Type mapping:
//   int          -> integer      i<n>e
//   bool         -> integer 0/1  (convenience; bencode has no boolean type)
//   string       -> byte string  <len>:<bytes>   (binary-safe)
//   list array   -> list         l...e           (per array_is_list())
//   assoc array  -> dictionary   d...e           (keys sorted by raw bytes)
//   object       -> dictionary   d...e           (forces a dict, incl. empty)
//
// Dictionary keys are coerced to strings and emitted in strcmp (raw byte)
// order, as the spec requires — callers never pre-sort. The empty array
// encodes as an empty list (le); when a dictionary may be empty (the scrape
// files-dict with no torrents) cast it to (object) so it always emits as a
// dict. Binary keys — e.g. raw 20-byte info_hashes — survive the object cast
// intact, so a stdClass is the canonical way to carry them.
function bencode_encode($value): string
{
    if (is_int($value)) {
        return 'i'.$value.'e';
    }
    if (is_bool($value)) {
        return 'i'.($value ? '1' : '0').'e';
    }
    if (is_string($value)) {
        return strlen($value).':'.$value;
    }

    ////	Dictionary
    // An object always (the explicit forced-dict form, including empty), or a
    // non-empty array whose keys aren't a 0..n-1 sequence. Keys sort by raw
    // byte order regardless of insertion order.
    if (is_object($value) || (is_array($value) && $value !== [] && ! array_is_list($value))) {
        $dict = (array) $value;
        ksort($dict, SORT_STRING);
        $out = 'd';
        foreach ($dict as $key => $item) {
            $key = (string) $key;
            $out .= strlen($key).':'.$key.bencode_encode($item);
        }

        return $out.'e';
    }

    ////	List
    // Sequential arrays, and the empty array, encode as a list.
    if (is_array($value)) {
        $out = 'l';
        foreach ($value as $item) {
            $out .= bencode_encode($item);
        }

        return $out.'e';
    }

    throw new \InvalidArgumentException(
        'bencode_encode: unsupported type '.gettype($value),
    );
}
