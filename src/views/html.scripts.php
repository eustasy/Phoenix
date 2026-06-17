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

/**
 * @param list<string> $extra_srcs
 */
function view_scripts_html(string $inline_js = '', array $extra_srcs = []): string
{
    $out = '<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>';
    foreach ($extra_srcs as $src) {
        $out .= "\n".'<script src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'"></script>';
    }
    $out .= "\n".'<script src="/assets/app.js"></script>';
    if ($inline_js !== '') {
        $out .= "\n".'<script>'.$inline_js.'</script>';
    }

    return $out;
}
