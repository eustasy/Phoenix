<?php

declare(strict_types=1);

////	peer_forwarded_list
// Parses a proxy forwarded-address header into an ordered list of normalised IP
// strings, left-to-right as written (so client-first, proxies appended). Handles
// two formats: the comma-separated X-Forwarded-For chain, and — when $rfc7239 is
// true — the RFC 7239 `Forwarded` header, taking the `for=` value of each
// element. Blank / unparseable entries (including RFC 7239 obfuscated
// identifiers) are dropped, so the result holds only valid IPs of either family.
/** @return array<int, string> */
function peer_forwarded_list(string $header, bool $rfc7239): array
{
    require_once __DIR__.'/peer.ip.normalize.php';

    $out = [];
    foreach (explode(',', $header) as $element) {
        if ($rfc7239) {
            // Each element is `;`-separated key=value pairs; we want `for=`.
            $token = null;
            foreach (explode(';', $element) as $pair) {
                $pair = trim($pair);
                if (stripos($pair, 'for=') === 0) {
                    $token = substr($pair, 4);
                    break;
                }
            }
            if ($token === null) {
                continue;
            }
        } else {
            $token = $element;
        }

        $ip = peer_ip_normalize($token);
        if ($ip !== null) {
            $out[] = $ip;
        }
    }

    return $out;
}
