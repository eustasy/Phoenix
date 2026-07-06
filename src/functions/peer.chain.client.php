<?php

declare(strict_types=1);

////	peer_chain_client
// Resolves the real client from a forwarded-address chain (a client-first list of
// normalised IPs — see peer_forwarded_list). Walks from the RIGHT, skipping
// entries that are themselves trusted proxies, and returns the first non-trusted
// address: the client the outermost trusted proxy actually observed. Correct
// whether the proxy appends or overwrites the header, and — unlike taking the
// leftmost entry — a client cannot spoof by pre-seeding the header, because any
// value it injects sits to the LEFT of the real address and the walk stops
// before reaching it.
//
// With an empty trusted_proxies list (only reachable when allow_any_proxy is on)
// nothing is skippable, so the rightmost entry — the nearest hop the direct peer
// forwarded — is returned. Returns null when the chain is empty or every entry is
// a trusted proxy.
/**
 * @param array<int, string> $chain
 * @param array<int, string> $trusted_proxies
 */
function peer_chain_client(array $chain, array $trusted_proxies): ?string
{
    require_once __DIR__.'/ip.in.cidr.php';

    for ($i = count($chain) - 1; $i >= 0; $i--) {
        $ip = $chain[$i];
        $is_trusted = false;
        foreach ($trusted_proxies as $cidr) {
            if (ip_in_cidr($ip, $cidr)) {
                $is_trusted = true;
                break;
            }
        }
        if (! $is_trusted) {
            return $ip;
        }
    }

    return null;
}
