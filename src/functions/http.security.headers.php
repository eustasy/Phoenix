<?php

declare(strict_types=1);

////	http_security_headers
// Emits the HTTP security-header set for a response, chosen by $profile. Call
// once per finalised response, before any body output — header() queues the
// header, and a later call with the same name replaces it, so re-emitting the
// universal nosniff from a deeper layer (e.g. tracker_error) is idempotent.
//
// Profiles map to the endpoint groups:
//   'tracker'      announce + scrape (bencode/XML/JSON) and every XML/JSON
//                  machine response — nosniff only, nothing that would encumber
//                  a non-document body.
//   'public_html'  the browser-facing HTML surfaces (index, scrape?stats,
//                  magnet) — nosniff + Referrer-Policy + a frame-permitting page
//                  CSP (frame-ancestors 'self', plus the legacy X-Frame-Options
//                  SAMEORIGIN fallback).
//   'admin'        admin panel + installer — the strict variant: DENY framing,
//                  no-referrer, no-store, and the page CSP with
//                  frame-ancestors 'none'.
//   'api'          the authenticated REST API (JSON/XML) — nosniff, DENY,
//                  no-store, and a locked-down default-src 'none' CSP (the API
//                  serves data, never assets).
//
// The page CSP is deliberately pragmatic, not strong. The HTML uses inline
// <script>/<style> blocks and inline style= attributes (no inline event
// handlers) and loads a handful of third-party CDN assets, so it ships
// 'unsafe-inline' plus the exact origins those assets come from. It is split by
// profile so public pages advertise only what they load: Google Fonts
// (fonts.googleapis.com + fonts.gstatic.com) and jsDelivr (Lucide icons) on
// both; a connect-src to api.pwnedpasswords.com (the set-password gate's
// client-side breach check) on ADMIN only.
//
// jsDelivr is in connect-src as well as script-src because the minified
// bundles advertise a //# sourceMappingURL, and a browser fetches that through
// connect-src when devtools is open. It costs nothing: script-src already
// trusts the origin, so this grants no reach an injected script did not have,
// and a CDN is not somewhere data can be exfiltrated TO. Without it every admin
// page logs a CSP violation the moment anyone opens devtools. Every script origin is jsDelivr —
// Lucide everywhere, plus jsVectorMap and Chart.js on admin pages — so there
// is one third-party script origin to trust rather than two. It still delivers frame-ancestors, object-src, base-uri and
// form-action, and blocks injected external script sources.

function http_security_headers(string $profile): void
{
    // Universal: never let a browser MIME-sniff a response into a type we did
    // not send. Applies to every profile, so emit it first for all of them.
    header('X-Content-Type-Options: nosniff');

    if ($profile === 'tracker') {
        return;
    }

    if ($profile === 'api') {
        // The API serves JSON/XML data and loads nothing, so lock it right down.
        header('X-Frame-Options: DENY');
        header('Cache-Control: no-store');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");

        return;
    }

    // The page CSP for the HTML surfaces, split by profile so public pages
    // advertise only what they load. Shared base first; script/style/connect and
    // frame-ancestors differ per profile.
    $base = "default-src 'self'; "
        ."font-src 'self' https://fonts.gstatic.com; "
        ."img-src 'self' data:; "
        ."object-src 'none'; "
        ."base-uri 'none'; "
        ."form-action 'self'; ";

    if ($profile === 'admin') {
        // Admin additionally loads jsVectorMap and Chart.js (both jsDelivr, the
        // same origin Lucide comes from), and the set-password gate runs a
        // client-side Pwned Passwords check against api.pwnedpasswords.com —
        // admin-only, so scoped here.
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
        header('Content-Security-Policy: '.$base
            ."script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; "
            ."connect-src 'self' https://api.pwnedpasswords.com https://cdn.jsdelivr.net; "
            ."frame-ancestors 'none'");

        return;
    }

    // 'public_html' (and any unrecognised profile falls through here). Public
    // pages load only Lucide (jsDelivr) + Google Fonts — no jsDelivr stylesheet,
    // no pwnedpasswords connect.
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Content-Security-Policy: '.$base
        ."script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        ."connect-src 'self' https://cdn.jsdelivr.net; "
        ."frame-ancestors 'self'");
}
