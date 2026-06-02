<?php

declare(strict_types=1);

////	peer_resolve_addresses
// Iterates the given address candidates, returning the first valid IPv4 and
// the first valid IPv6 found, plus their associated ports (or false when no
// port could be parsed). When $reject_private is true, private/reserved
// addresses are skipped — so a private REMOTE_ADDR (NAT/proxy) falls through
// to a lower-priority public candidate (e.g. a client-declared external_ip),
// which is exactly the case BEP 3's `ip` parameter exists for.

/**
 * @param array<int, string> $addresses
 * @return array<string, string|int|false>
 */
function peer_resolve_addresses(array $addresses, bool $reject_private = false): array
{
    require_once __DIR__.'/parse.ipv4.php';
    require_once __DIR__.'/parse.ipv6.php';

    $result = [
        'ipv4' => false,
        'ipv6' => false,
        'portv4' => false,
        'portv6' => false,
    ];
    foreach ($addresses as $address) {
        if (! $result['ipv4'] && ($ipv4 = parse_ipv4($address, $reject_private)) !== false) {
            $result['ipv4'] = $ipv4['ip'];
            $result['portv4'] = $ipv4['port'];
        }
        if (! $result['ipv6'] && ($ipv6 = parse_ipv6($address, $reject_private)) !== false) {
            $result['ipv6'] = $ipv6['ip'];
            $result['portv6'] = $ipv6['port'];
        }
    }

    return $result;
}
