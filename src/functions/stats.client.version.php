<?php

declare(strict_types=1);

////	stats_client_version
// Renders the four Azureus version characters as a dotted version
// ('4620' -> '4.6.2.0'). Returns '' when any character is non-numeric, so a
// best-effort label drops the version rather than emitting nonsense.
function stats_client_version(string $chars): string
{
    if (strlen($chars) !== 4 || ! ctype_digit($chars)) {
        return '';
    }

    return implode('.', str_split($chars));
}
