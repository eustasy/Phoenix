<?php

declare(strict_types=1);

////	ip_in_cidr
// Returns true when $ip falls inside the CIDR block $cidr (e.g. "10.0.0.0/8"
// or "2001:db8::/32"). A $cidr with no "/" is treated as a single-address
// match. Works for both IPv4 and IPv6; an address and a block of different
// families never match. Any malformed input returns false.
function ip_in_cidr(string $ip, string $cidr): bool
{
    $slash = strpos($cidr, '/');
    if ($slash === false) {
        $subnet = $cidr;
        $bits = -1; // sentinel: match the full address width
    } else {
        $subnet = substr($cidr, 0, $slash);
        $bits_str = substr($cidr, $slash + 1);
        if ($bits_str === '' || ! ctype_digit($bits_str)) {
            return false;
        }
        $bits = (int) $bits_str;
    }

    // Pre-validate so inet_pton only ever sees well-formed addresses (and so
    // there is no warning to suppress).
    if (filter_var($ip, FILTER_VALIDATE_IP) === false || filter_var($subnet, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    $ip_bin = inet_pton($ip);
    $subnet_bin = inet_pton($subnet);
    if ($ip_bin === false || $subnet_bin === false || strlen($ip_bin) !== strlen($subnet_bin)) {
        return false;
    }

    $max_bits = strlen($ip_bin) * 8;
    if ($bits === -1) {
        $bits = $max_bits;
    }
    if ($bits < 0 || $bits > $max_bits) {
        return false;
    }

    // Compare the whole bytes covered by the prefix, then the leftover bits.
    $full_bytes = intdiv($bits, 8);
    if ($full_bytes > 0 && substr($ip_bin, 0, $full_bytes) !== substr($subnet_bin, 0, $full_bytes)) {
        return false;
    }

    $remaining = $bits % 8;
    if ($remaining === 0) {
        return true;
    }

    $mask = 0xff << (8 - $remaining) & 0xff;

    return (ord($ip_bin[$full_bytes]) & $mask) === (ord($subnet_bin[$full_bytes]) & $mask);
}
