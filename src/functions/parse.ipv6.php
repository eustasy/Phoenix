<?php

declare(strict_types=1);

////	parse_ipv6
// Attempts to parse an address string as IPv6, optionally bracketed with `[ip]:port`.
// When $reject_private is true, private (ULA fc00::/7) and reserved ranges are
// rejected so they can never be handed out as routable peer addresses — see
// reject_private_ips in phoenix.default.php.
// Returns array('ip' => string, 'port' => int|false) on success, or false if the address is not valid IPv6.
/** @return array{ip: string, port: int|false}|false */
function parse_ipv6(string $address, bool $reject_private = false): array|false
{
    $candidate = $address;
    $port = false;
    if (strpos($candidate, ']:') !== false) {
        $parts = explode(']:', $candidate, 2);
        $candidate = $parts[0];
        if (ctype_digit($parts[1])) {
            $port = intval($parts[1]);
        }
    }
    if (strpos($candidate, '[') !== false) {
        $candidate = trim($candidate, '[]');
    }
    $flags = FILTER_FLAG_IPV6;
    if ($reject_private) {
        $flags |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    }
    if (! filter_var($candidate, FILTER_VALIDATE_IP, $flags)) {
        return false;
    }

    return ['ip' => $candidate, 'port' => $port];
}
