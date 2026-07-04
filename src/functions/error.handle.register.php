<?php

declare(strict_types=1);

////	error_handle_register
// Registers process-global handlers that route uncaught exceptions and fatal
// shutdown errors through phoenix_hook_event('error', ...) so an external
// monitor (e.g. the src/hooks/phoenix.error.php Sentry handler) sees them.
// Called from phoenix.php only when $settings['report_errors'] is on, so a
// default install registers nothing and behaves exactly as before.
//
// The handlers report and log; they do not try to render a tracker response. An
// uncaught exception or fatal is a bug, not a client-facing condition — the
// point here is visibility, and the existing baseline (log_errors=1,
// display_errors=0) still governs what the client sees.

function error_handle_register(): void
{
    require_once __DIR__.'/phoenix.hook.event.php';

    set_exception_handler(static function (\Throwable $e): void {
        phoenix_hook_event('error', [
            'throwable' => $e,
            'level' => 'error',
            'source' => 'uncaught_exception',
        ]);
        // Preserve the default log line (set_exception_handler replaces it).
        error_log('Uncaught '.$e::class.': '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if (
            $error === null ||
            ! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
        ) {
            return;
        }
        phoenix_hook_event('error', [
            'message' => $error['message'],
            'level' => 'fatal',
            'source' => 'shutdown',
            'file' => $error['file'],
            'line' => $error['line'],
        ]);
    });
}
