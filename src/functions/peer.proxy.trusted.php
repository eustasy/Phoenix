<?php

declare(strict_types=1);

////	peer_proxy_trusted
// Whether the X-Forwarded-For / Client-IP headers on this request should be
// trusted. With no trusted_proxies configured the answer is yes (trust any —
// for proxies that have no stable IP range to pin to). Once ranges are listed,
// the headers are only honored when the connecting REMOTE_ADDR falls inside
// one of them — so a client connecting directly to the origin cannot spoof.
/**
 * @param array<string, mixed> $server
 * @param array<int, string> $trusted_proxies
 */
function peer_proxy_trusted(array $server, array $trusted_proxies): bool
{
    if ($trusted_proxies === []) {
        return true;
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
