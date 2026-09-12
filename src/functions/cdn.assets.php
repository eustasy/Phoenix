<?php

declare(strict_types=1);

////	cdn_assets
// The third-party assets Phoenix loads from a CDN, each pinned to an exact
// version and carrying the Subresource Integrity hash of that exact file.
//
// One table because a URL and its hash have to move together: bump a version
// without recomputing the hash and the browser refuses the file, silently, and
// the page loses its icons or its chart with nothing in the console but an SRI
// error. Keeping both on one line makes the pairing hard to get wrong, and
// means a version bump is one edit rather than four files.
//
// Every entry is on cdn.jsdelivr.net — the single third-party script origin the
// CSP in http_security_headers() allows. Adding an asset from anywhere else
// means widening that policy too.
//
// To bump a version, change the URL and recompute:
//   curl -sL <url> | openssl dgst -sha256 -binary | openssl base64 -A
//
// 'integrity' is the full attribute value including the 'sha256-' prefix, ready
// to emit. Returns [key => ['url' => string, 'integrity' => string], …].

/** @return array<string, array{url: string, integrity: string}> */
function cdn_assets(): array
{
    return [
        // Icons, on every HTML page.
        'lucide' => [
            'url' => 'https://cdn.jsdelivr.net/npm/lucide@1.44.0/dist/umd/lucide.min.js',
            'integrity' => 'sha256-VSX/Muw2o9JWn2tkUpFFTU3+eQfHMfHo7BqP2qnki/0=',
        ],
        // Charts: the dashboard, Clients and Bandwidth.
        'chart' => [
            'url' => 'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js',
            'integrity' => 'sha256-SERKgtTty1vsDxll+qzd4Y2cF9swY9BCq62i9wXJ9Uo=',
        ],
        // The Geography map: library, world map data, and the library's CSS.
        'jsvectormap' => [
            'url' => 'https://cdn.jsdelivr.net/npm/jsvectormap@1.7.0/dist/jsvectormap.min.js',
            'integrity' => 'sha256-yjoBT8ZtgknE2gcA8yvWExzw8LsIAfj6/r/XAw889Qw=',
        ],
        'jsvectormap_world' => [
            'url' => 'https://cdn.jsdelivr.net/npm/jsvectormap@1.7.0/dist/maps/world.js',
            'integrity' => 'sha256-3jwsIc9jvdlaTPxHelZqSxiVedrrJhV+jssQEAAiTt0=',
        ],
        'jsvectormap_css' => [
            'url' => 'https://cdn.jsdelivr.net/npm/jsvectormap@1.7.0/dist/jsvectormap.min.css',
            'integrity' => 'sha256-Zshqz6X+Rs2Rf5sPtiSba+rwLBL1ZTyLBppbYOstR9M=',
        ],
    ];
}
