<?php

declare(strict_types=1);

////	peer_xff_first
// Returns the first non-blank entry of a comma-separated proxy header chain
// (`client, proxy1, ...` per RFC 7239) — the originating client — or null
// when every entry is blank.
function peer_xff_first(string $header): ?string
{
    foreach (explode(',', $header) as $entry) {
        $entry = trim($entry);
        if ($entry !== '') {
            return $entry;
        }
    }

    return null;
}
