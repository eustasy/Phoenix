<?php

declare(strict_types=1);

////	view_filename_html
// Render a torrent's filename as a table cell's contents, for the Torrents and
// Bandwidth listings — the two places it gets a column of its own.
//
// The filename is what the torrent actually delivers, and routinely the thing
// that distinguishes two rows sharing a display name. It is also longer than
// the display name, so the cell truncates (.ph-file) rather than wrapping and
// doubling every row's height.
//
// The tooltip is the point of this being shared. A title that repeats a value
// the reader can already see tells them nothing, so it is emitted only when the
// name is long enough that the column will have cut it — at which point it is
// the only way to read the rest. VISIBLE_CHARS mirrors the max-width in
// phoenix.css; the two have to move together, and having them in one function
// is what makes that tractable.
//
// The threshold is deliberately approximate: the column is sized in ch against
// a monospace face, so for this content the count is the width. A proportional
// face would make it a guess, and the cost of guessing is an occasional
// redundant tooltip rather than a missing one.
//
// $empty_class is the caller's own muted/dim convention for an absent value.
// Returns the cell's inner HTML, already escaped.

function view_filename_html(?string $filename, string $empty_class = 'muted'): string
{
    // Keep in step with .ph-file { max-width } in public/assets/phoenix.css.
    $visible_chars = 46;

    if ($filename === null || $filename === '') {
        return '<span class="'.$empty_class.'">&mdash;</span>';
    }

    $escaped = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');

    // Encoding named explicitly: mb_strlen() otherwise follows
    // mb_internal_encoding(), which is not UTF-8 everywhere, and counting a
    // multi-byte name as bytes puts a tooltip on a filename that fits.
    if (mb_strlen($filename, 'UTF-8') <= $visible_chars) {
        return '<span class="mono text-sm ph-file">'.$escaped.'</span>';
    }

    return '<abbr class="ph-plain mono text-sm ph-file" title="'.$escaped.'">'.$escaped.'</abbr>';
}
