<?php

declare(strict_types=1);

////	phoenix_hook
// Includes the named hook file from src/hooks/ when it exists.
// Hook code runs in this function's local scope and so can read and write
// $connection, $settings, $time, and $peer. $peer is passed by reference
// so hooks may mutate it for the remainder of the request lifecycle.
function phoenix_hook(string $name, mysqli $connection, array $settings, int $time, array &$peer): void
{
    $path = __DIR__.'/../hooks/phoenix.'.$name.'.php';
    if (is_readable($path)) {
        include_once $path;
    }
}
