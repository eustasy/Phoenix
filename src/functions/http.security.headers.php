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
// 'unsafe-inline' plus the exact origins those assets come from: Google Fonts
// (fonts.googleapis.com CSS + fonts.gstatic.com font files), unpkg (Lucide
// icons JS), and jsDelivr (jsVectorMap CSS/JS/world map data). It still delivers
// frame-ancestors, object-src, base-uri and form-action, and blocks injected
// external script sources. Tightening it — nonces, self-hosting the CDN assets,
// dropping 'unsafe-inline', pinning versions + SRI — is a tracked follow-up.

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

    // The shared page CSP for the HTML surfaces (admin + public HTML). Only the
    // frame-ancestors directive differs by profile, so it is appended per-branch.
    $page_csp = "default-src 'self'; "
        ."script-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net; "
        ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; "
        ."font-src 'self' https://fonts.gstatic.com; "
        ."img-src 'self' data:; "
        ."connect-src 'self'; "
        ."object-src 'none'; "
        ."base-uri 'none'; "
        ."form-action 'self'; ";

    if ($profile === 'admin') {
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
        header('Content-Security-Policy: '.$page_csp."frame-ancestors 'none'");

        return;
    }

    // 'public_html' (and any unrecognised profile falls through to this safe,
    // browser-facing default).
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Content-Security-Policy: '.$page_csp."frame-ancestors 'self'");
}
