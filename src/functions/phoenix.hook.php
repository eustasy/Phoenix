<?php

declare(strict_types=1);

////	phoenix_hook
// Includes the named hook file from src/hooks/ when it exists.
// Hook code runs in this function's local scope and so can read and write
// $connection, $settings, $time, and $peer. $peer is passed by reference
// so hooks may mutate it for the remainder of the request lifecycle.
//
// Plain include, not include_once: hooks are event handlers, and persistent
// runtimes (PHP-FPM workers, the built-in server) serve many requests per
// process — include_once would fire each hook once per process and then
// silently no-op. Hook files therefore must not declare functions/classes
// at their top level; require_once any helpers instead.
/**
 * @param PhoenixSettings $settings
 * @param array<string, mixed> $peer
 */
function phoenix_hook(string $name, mysqli $connection, array $settings, int $time, array &$peer): void
{
    $path = __DIR__.'/../hooks/phoenix.'.$name.'.php';
    if (is_readable($path)) {
        include $path;
    }
}
