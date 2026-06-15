<?php

declare(strict_types=1);

////	view_scripts_html
// The trailing <script> tags shared by every page: the Lucide icon library
// (icons degrade to nothing when offline — labels still read), any per-page
// library sources ($extra_srcs, e.g. jsVectorMap on the Geography page), the
// shared Phoenix helpers (assets/app.js), and a final inline block that runs
// $inline_js and always re-renders icons. $inline_js is trusted (built by the
// page); $extra_srcs are URLs and are attribute-escaped.

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
    $out .= "\n".'<script>'.$inline_js."\nphInitIcons();\n".'</script>';

    return $out;
}
