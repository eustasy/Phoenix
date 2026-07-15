<?php

declare(strict_types=1);

////	peer_address_candidates
// Builds an ordered list of candidate IP addresses (highest priority first) from
// $settings, $_GET and $_SERVER, for peer_resolve_addresses() to turn into an
// IPv4/IPv6 pair. Priority, high to low:
//   1. forwarded-address headers, in the operator's forwarded_headers order —
//      but ONLY when the direct connection is a trusted proxy
//      (peer_proxy_trusted). Chain headers (X-Forwarded-For, Forwarded) are
//      resolved with a rightmost-untrusted-hop walk so a client cannot spoof by
//      pre-seeding them; single-value headers (X-Real-IP, CF-Connecting-IP,
//      True-Client-IP, Client-IP) are taken as the proxy set them.
//   2. REMOTE_ADDR — the direct TCP peer, always a candidate.
//   3. client-declared IP params, allow_client_ip (?ip / ?ipv4 / ?ipv6), lowest priority.
// Families mix freely: peer_resolve_addresses() picks the first valid address of
// each family independently, so a header supplying one family and REMOTE_ADDR the
// other both land.
/**
 * @param PhoenixSettings $settings
 * @param array<string, mixed> $get
 * @param array<string, mixed> $server
 * @return array<int, string>
 */
function peer_address_candidates(array $settings, array $get, array $server): array
{
    require_once __DIR__.'/peer.proxy.trusted.php';
    require_once __DIR__.'/peer.forwarded.list.php';
    require_once __DIR__.'/peer.chain.client.php';
    require_once __DIR__.'/peer.ip.normalize.php';

    // Recognised forwarded-address headers → their $_SERVER key and whether the
    // value is a chain (rightmost-walked) or a single proxy-set address.
    $header_map = [
        'x-forwarded-for' => ['key' => 'HTTP_X_FORWARDED_FOR', 'chain' => true, 'rfc7239' => false],
        'forwarded' => ['key' => 'HTTP_FORWARDED', 'chain' => true, 'rfc7239' => true],
        'x-real-ip' => ['key' => 'HTTP_X_REAL_IP', 'chain' => false, 'rfc7239' => false],
        'cf-connecting-ip' => ['key' => 'HTTP_CF_CONNECTING_IP', 'chain' => false, 'rfc7239' => false],
        'true-client-ip' => ['key' => 'HTTP_TRUE_CLIENT_IP', 'chain' => false, 'rfc7239' => false],
        'client-ip' => ['key' => 'HTTP_CLIENT_IP', 'chain' => false, 'rfc7239' => false],
    ];

    // Built lowest-priority first, then reversed at the end — so allow_client_ip sinks
    // to the bottom and the forwarded headers rise to the top.
    $addresses = [];

    if ($settings['allow_client_ip']) {
        foreach (['ip', 'ipv4', 'ipv6'] as $param) {
            if (isset($get[$param]) && is_string($get[$param])) {
                $addresses[] = $get[$param];
            }
        }
    }

    if (isset($server['REMOTE_ADDR']) && is_string($server['REMOTE_ADDR'])) {
        $addresses[] = $server['REMOTE_ADDR'];
    }

    if (
        $settings['forwarded_headers'] !== []
        && peer_proxy_trusted($server, $settings['trusted_proxies'], $settings['trust_any_forwarded'])
    ) {
        // Reverse the operator's list so its FIRST entry ends up highest priority
        // after the final array_reverse below.
        foreach (array_reverse($settings['forwarded_headers']) as $name) {
            $name = strtolower(trim($name));
            if (! isset($header_map[$name])) {
                continue;
            }
            $spec = $header_map[$name];
            if (! isset($server[$spec['key']]) || ! is_string($server[$spec['key']])) {
                continue;
            }

            $client = $spec['chain']
                ? peer_chain_client(peer_forwarded_list($server[$spec['key']], $spec['rfc7239']), $settings['trusted_proxies'])
                : peer_ip_normalize($server[$spec['key']]);

            if ($client !== null) {
                $addresses[] = $client;
            }
        }
    }

    return array_reverse($addresses);
}
