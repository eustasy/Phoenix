<?php

declare(strict_types=1);

// Accepts a raw query-string value (not a $_GET value, which is already decoded).
// Calls urldecode() itself so binary bytes in %XX form are resolved exactly once.
//
// Normalizes info_hash and peer_id to 40-char hex before they reach any query.
// Queries bind these as parameters (mysqli_execute_query), which is the actual
// SQL-injection defense; this sanitizer is input validation — it rejects
// malformed values early and keeps the stored/compared form consistent.
function maybe_binary_to_hex(string $binary): string|false
{
    $binary = urldecode($binary);
    // BEP 3: info_hash and peer_id are 20-byte SHA-1 values, URL-encoded as raw binary.
    // Some clients send them pre-encoded as 40-char hex strings; both forms are valid.
    if (strlen($binary) === 20) {
        $binary = bin2hex($binary);
    }
    if (strlen($binary) === 40 && ctype_xdigit($binary)) {
        return $binary;
    }

    return false;
}
