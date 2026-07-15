<?php

declare(strict_types=1);

////	peer_proxy_trusted
// Whether the forwarded-address headers on this request should be trusted — i.e.
// whether the direct connection came from a reverse proxy we trust. With
// trusted_proxies listed, the headers are honored only when the connecting
// REMOTE_ADDR falls inside one of the ranges, so a client connecting straight to
// the origin cannot spoof. With trusted_proxies EMPTY the answer is
// $trust_any_forwarded: false (the default) fails safe and trusts nothing; true is
// the explicit, documented opt-in to "trust any peer" for proxies that have no
// stable IP range to pin to.
/**
 * @param array<string, mixed> $server
 * @param array<int, string> $trusted_proxies
 */
function peer_proxy_trusted(array $server, array $trusted_proxies, bool $trust_any_forwarded): bool
{
    if ($trusted_proxies === []) {
        return $trust_any_forwarded;
    }
    if (! isset($server['REMOTE_ADDR']) || ! is_string($server['REMOTE_ADDR'])) {
        return false;
    }

    require_once __DIR__.'/ip.in.cidr.php';
    foreach ($trusted_proxies as $cidr) {
        if (ip_in_cidr($server['REMOTE_ADDR'], $cidr)) {
            return true;
        }
    }

    return false;
}
