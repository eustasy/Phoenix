<?php

declare(strict_types=1);

////	peer_external_ip
// Picks the client's own public address to report back under BEP 24's
// 'external ip' announce key. When the peer resolved on both families, prefers
// the one the request actually arrived on (per REMOTE_ADDR) so the client
// learns the address relevant to this connection; otherwise returns whichever
// family resolved. Returns the address string, or false when neither is set.
/**
 * @param array<string, mixed> $peer
 * @param array<string, mixed> $server
 */
function peer_external_ip(array $peer, array $server): string|false
{
    $ipv4 = $peer['ipv4'] ?? false;
    $ipv6 = $peer['ipv6'] ?? false;
    $ipv4 = is_string($ipv4) && $ipv4 !== '' ? $ipv4 : false;
    $ipv6 = is_string($ipv6) && $ipv6 !== '' ? $ipv6 : false;

    // A colon in the connecting address means the request arrived over IPv6.
    $remote = $server['REMOTE_ADDR'] ?? '';
    $arrived_v6 = is_string($remote) && str_contains($remote, ':');

    if ($arrived_v6) {
        return $ipv6 !== false ? $ipv6 : $ipv4;
    }

    return $ipv4 !== false ? $ipv4 : $ipv6;
}
