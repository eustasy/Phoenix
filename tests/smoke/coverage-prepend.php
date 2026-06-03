<?php

declare(strict_types=1);

// Server-side coverage collector for the smoke suite. Set as the built-in
// server's auto_prepend_file (with pcov.enabled=1). PCOV only records between
// \pcov\start() and \pcov\stop(), so we start collection here (before the entry
// point runs) and, on shutdown, stop + dump this request's line data as JSON to
// a unique file under SMOKE_COV_DIR. tests/smoke/merge-coverage.php later unions
// those into Clover. A no-op when PCOV isn't loaded or SMOKE_COV_DIR isn't set,
// so the server runs fine without coverage too.

if (! function_exists('pcov\collect')) {
    return;
}

\pcov\start();

register_shutdown_function(static function (): void {
    \pcov\stop();

    $dir = getenv('SMOKE_COV_DIR');
    if ($dir === false || $dir === '') {
        return;
    }
    if (! is_dir($dir)) {
        @mkdir($dir, 0o777, true);
    }

    // \pcov\collect() defaults to \pcov\all: every instrumented file (under
    // pcov.directory, minus pcov.exclude) with per-line hit counts.
    @file_put_contents(
        $dir.'/'.bin2hex(random_bytes(8)).'.json',
        (string) json_encode(\pcov\collect()),
    );
});
