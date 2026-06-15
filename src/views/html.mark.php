<?php

declare(strict_types=1);

////	view_mark_html
// The Phoenix flame mark, inlined as SVG so it scales crisply and recolours
// with the theme (the disc tracks --color-action; the flame layers are warm).
// Used by every page chrome (public header, auth brand, admin sidebar). The
// class hooks (.ph-mark*) are styled in assets/phoenix.css.
// Returns an HTML string.

function view_mark_html(): string
{
    return '<svg class="ph-mark" viewBox="0 0 64 64" role="img" aria-label="Phoenix" xmlns="http://www.w3.org/2000/svg">'.
        '<circle class="ph-mark-disc" cx="32" cy="32" r="32"/>'.
        '<path class="ph-mark-flame-o" d="M32 6C40 18 47 24 47 34C47 43.7 40.3 50 32 50C23.7 50 17 43.7 17 34C17 24 25 18 32 6Z"/>'.
        '<path class="ph-mark-flame-m" d="M32 17C37.5 25 42 29 42 35.5C42 41.5 37.5 46 32 46C26.5 46 22 41.5 22 35.5C22 29 27 24 32 17Z"/>'.
        '<path class="ph-mark-core" d="M32 28C35 32.5 37 35 37 38.5C37 42 34.8 44.5 32 44.5C29.2 44.5 27 42 27 38.5C27 35 29 32.5 32 28Z"/>'.
        '</svg>';
}
