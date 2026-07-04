<?php

declare(strict_types=1);

////	phoenix_hook_event
// Fires a peer-free system/observability event to its src/hooks/ handler, if one
// exists. Sibling to phoenix_hook() (the peer-lifecycle dispatcher): that one
// needs a live $connection/$time/$peer and only fires mid-announce, so it cannot
// carry an error. This one takes a single $context array and is safe to call
// from anywhere — bootstrap (before the DB connects), tracker_error(), a global
// exception/shutdown handler. The handler file (src/hooks/phoenix.<name>.php)
// reads $context, e.g. ['throwable' => $e] or ['message' => ..., 'level' => ...].
//
// Hardened for the error path, where the handler is operator-supplied and must
// never be able to take down the request it is reporting on:
//   * the include is wrapped, so a throwing handler degrades to error_log()
//     instead of propagating;
//   * a re-entrancy guard drops any event fired from *inside* a handler (an
//     error raised while reporting an error would otherwise recurse forever);
//   * handler existence is resolved once per process, keeping a fire down to an
//     array lookup once warm (no stat() per announce error).
// Consequently a newly-added hook file is picked up on the next worker, not
// mid-process — matching FPM's recycle model.
/** @param array<string, mixed> $context */
function phoenix_hook_event(string $name, array $context = []): void
{
    /** @var bool $reentrant */
    static $reentrant = false;
    /** @var array<string, string|false> $resolved */
    static $resolved = [];

    // An event fired from within a handler: do not recurse. The outer call owns
    // reporting for this request.
    if ($reentrant) {
        return;
    }

    if (! array_key_exists($name, $resolved)) {
        $candidate = __DIR__.'/../hooks/phoenix.'.$name.'.php';
        $resolved[$name] = is_readable($candidate) ? $candidate : false;
    }
    $path = $resolved[$name];
    if ($path === false) {
        return;
    }

    $reentrant = true;
    try {
        include $path;
    } catch (\Throwable $e) {
        // The reporter itself failed. Last-resort log so the event is not lost,
        // then swallow — an error handler must never become the error.
        error_log('phoenix_hook_event("'.$name.'") handler failed: '.$e->getMessage());
    } finally {
        $reentrant = false;
    }
}
