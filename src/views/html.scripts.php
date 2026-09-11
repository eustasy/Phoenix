<?php

declare(strict_types=1);

////	view_scripts_html
// The trailing <script> tags shared by every page: the Lucide icon library
// (icons degrade to nothing when offline — labels still read), any per-page
// script sources ($extra_srcs, e.g. the feature/page .js files and jsVectorMap
// on Geography), and the shared Phoenix helpers (assets/app.js, which renders
// icons on load). A final inline <script> is emitted ONLY when $inline_js is
// non-empty — reserved for the rare page that must inline JS to receive PHP
// data (the magnet/geography "_" files, read in and prefixed with their data).
// $inline_js is trusted (built by the page); $extra_srcs are URLs and are
// attribute-escaped.
//
// Any src listed in cdn_assets() is emitted with its Subresource Integrity hash
// and crossorigin="anonymous", so a CDN serving something other than the pinned
// file is refused rather than run. The lookup is by URL, so a caller passes the
// URL as it always did and cannot forget the hash.

/**
 * @param list<string> $extra_srcs
 */
function view_scripts_html(string $inline_js = '', array $extra_srcs = []): string
{
    require_once __DIR__.'/../functions/cdn.assets.php';

    // URL => integrity, so a src can be matched however the caller obtained it.
    $integrity = [];
    foreach (cdn_assets() as $asset) {
        $integrity[$asset['url']] = $asset['integrity'];
    }

    $tag = static function (string $src) use ($integrity): string {
        $attrs = '';
        if (isset($integrity[$src])) {
            $attrs = ' integrity="'.htmlspecialchars($integrity[$src], ENT_QUOTES, 'UTF-8').'"'.
                ' crossorigin="anonymous"';
        }

        return '<script src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'"'.$attrs.'></script>';
    };

    $out = $tag(cdn_assets()['lucide']['url']);
    foreach ($extra_srcs as $src) {
        $out .= "\n".$tag($src);
    }
    $out .= "\n".'<script src="/assets/app.js"></script>';
    if ($inline_js !== '') {
        $out .= "\n".'<script>'.$inline_js.'</script>';
    }

    return $out;
}
