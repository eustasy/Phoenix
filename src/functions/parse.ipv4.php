<?php

declare(strict_types=1);

////	parse_ipv4
// Attempts to parse an address string as IPv4, optionally with a `:port` suffix.
// Strips a leading `::ffff:` IPv4-mapped IPv6 prefix when present.
// When $reject_private is true, private (RFC 1918) and reserved ranges are
// rejected so they can never be handed out as routable peer addresses — see
// reject_private_ips in phoenix.default.php.
// Returns array('ip' => string, 'port' => int|false) on success, or false if the address is not valid IPv4.
/** @return array{ip: string, port: int|false}|false */
function parse_ipv4(string $address, bool $reject_private = false): array|false
{
    // IPv4-mapped IPv6 prefix is a literal 7-char string, not a character mask;
    // trim() would also chew leading/trailing 'f' and ':' from the address proper.
    $candidate = str_starts_with($address, '::ffff:') ? substr($address, 7) : $address;
    $port = false;
    if (strpos($candidate, ':') !== false) {
        $parts = explode(':', $candidate, 2);
        // A colon followed by non-numeric characters is malformed.
        if (! ctype_digit($parts[1])) {
            return false;
        }
        $candidate = $parts[0];
        $port = intval($parts[1]);
    }
    $flags = FILTER_FLAG_IPV4;
    if ($reject_private) {
        $flags |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    }
    if (! filter_var($candidate, FILTER_VALIDATE_IP, $flags)) {
        return false;
    }

    return ['ip' => $candidate, 'port' => $port];
}
