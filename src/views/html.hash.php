<?php

declare(strict_types=1);

////	view_hash_html
// A truncated, monospace info-hash cell with a click-to-copy button. The short
// form (first $short hex chars) is shown; the full hash rides on data-hash so
// phCopyHash() (assets/app.js) can copy it. Hashes are 40-char hex, but the
// value is attribute-escaped defensively all the same. Used by the public index
// and every admin torrent/peer table. Returns an HTML fragment for a table cell.

function view_hash_html(string $hash, int $short = 12): string
{
    $full = htmlspecialchars($hash, ENT_QUOTES, 'UTF-8');
    $text = htmlspecialchars(substr($hash, 0, $short), ENT_QUOTES, 'UTF-8');

    return '<span class="hash">'.
        '<span class="hash-text">'.$text.'</span>'.
        '<button class="hash-copy" title="Copy info hash" data-hash="'.$full.'" onclick="phCopyHash(this)">'.
        '<span class="ph-ico" data-lucide="copy"></span>'.
        '</button>'.
        '</span>';
}
