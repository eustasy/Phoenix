<?php

declare(strict_types=1);

////	peer_ip_normalize
// Normalises a single address token from a forwarded-address header into a bare,
// validated IP string (either family) — or null when it isn't one. Handles the
// forms these headers use: surrounding double quotes (RFC 7239 quoted-strings),
// a bracketed IPv6 literal with an optional port (`[2001:db8::1]:4711`), and an
// IPv4 `host:port`. A bare IPv6 (several colons, no brackets) is never
// port-stripped. RFC 7239 obfuscated identifiers (`_hidden`, `unknown`) and
// anything malformed return null.
function peer_ip_normalize(string $raw): ?string
{
    $value = trim($raw);
    // Strip one layer of surrounding double quotes (RFC 7239 quoted-strings).
    if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
        $value = trim(substr($value, 1, -1));
    }
    if ($value === '') {
        return null;
    }

    if ($value[0] === '[') {
        // Bracketed IPv6: [addr] or [addr]:port — take what is inside the brackets.
        $close = strpos($value, ']');
        if ($close === false) {
            return null;
        }
        $ip = substr($value, 1, $close - 1);
    } elseif (substr_count($value, ':') === 1) {
        // Exactly one colon → IPv4 host:port (a bare IPv6 has several colons).
        $ip = substr($value, 0, (int) strpos($value, ':'));
    } else {
        // No colon (IPv4) or many colons (bare IPv6) — take as-is.
        $ip = $value;
    }

    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
}
