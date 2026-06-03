<?php

declare(strict_types=1);

// Server-side coverage collector for the smoke suite. Set as the built-in
// server's auto_prepend_file (with pcov.enabled=1). On each request's shutdown
// it dumps the cumulative PCOV line data to a unique JSON file under
// SMOKE_COV_DIR; tests/smoke/merge-coverage.php later unions those into Clover.
// A no-op when PCOV isn't loaded or SMOKE_COV_DIR isn't set, so the server runs
// fine without coverage too.

if (! function_exists('pcov\collect')) {
    return;
}

register_shutdown_function(static function (): void {
    $dir = getenv('SMOKE_COV_DIR');
    if ($dir === false || $dir === '') {
        return;
    }
    if (! is_dir($dir)) {
        @mkdir($dir, 0o777, true);
    }

    $data = \pcov\collect();
    @file_put_contents(
        $dir.'/'.bin2hex(random_bytes(8)).'.json',
        (string) json_encode($data),
    );
});
