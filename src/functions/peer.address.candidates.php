<?php

declare(strict_types=1);

////	peer_address_candidates
// Builds an ordered list of candidate IP addresses from $settings, $_GET, and
// $_SERVER. The list is reversed before return so trusted-last sources
// (X-Forwarded-For / Client-IP, when honor_xff is enabled) take precedence
// over REMOTE_ADDR, which in turn takes precedence over client-supplied
// values from external_ip.
/**
 * @param PhoenixSettings $settings
 * @param array<string, mixed> $get
 * @param array<string, mixed> $server
 * @return array<int, string>
 */
function peer_address_candidates(array $settings, array $get, array $server): array
{
    require_once __DIR__.'/peer.proxy.trusted.php';
    require_once __DIR__.'/peer.xff.first.php';

    $addresses = [];
    if ($settings['external_ip']) {
        if (isset($get['ip']) && is_string($get['ip'])) {
            $addresses[] = $get['ip'];
        }
        if (isset($get['ipv4']) && is_string($get['ipv4'])) {
            $addresses[] = $get['ipv4'];
        }
        if (isset($get['ipv6']) && is_string($get['ipv6'])) {
            $addresses[] = $get['ipv6'];
        }
    }
    if (isset($server['REMOTE_ADDR']) && is_string($server['REMOTE_ADDR'])) {
        $addresses[] = $server['REMOTE_ADDR'];
    }
    if ($settings['honor_xff'] && peer_proxy_trusted($server, $settings['trusted_proxies'])) {
        // Both headers can carry a comma-separated chain (`client, proxy1, ...`).
        // Take the first entry — the originating client per RFC 7239 — and skip
        // the header entirely if every entry is blank. The proxy must
        // strip/sanitise these; peer_proxy_trusted() further restricts trust to
        // the configured trusted_proxies ranges (empty = trust any peer).
        if (isset($server['HTTP_CLIENT_IP']) && is_string($server['HTTP_CLIENT_IP'])) {
            $first = peer_xff_first($server['HTTP_CLIENT_IP']);
            if ($first !== null) {
                $addresses[] = $first;
            }
        }
        if (isset($server['HTTP_X_FORWARDED_FOR']) && is_string($server['HTTP_X_FORWARDED_FOR'])) {
            $first = peer_xff_first($server['HTTP_X_FORWARDED_FOR']);
            if ($first !== null) {
                $addresses[] = $first;
            }
        }
    }

    return array_reverse($addresses);
}
