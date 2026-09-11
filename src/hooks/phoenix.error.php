<?php

declare(strict_types=1);

////	Error
// A server-side error occurred. Fired by phoenix_hook_event('error', ...) from
// tracker_error()'s server-fault path, the wrapped model writes (a caught
// mysqli_sql_exception), and — when $settings['report_errors'] is on — the
// global uncaught-exception / fatal shutdown handlers. Only fires at all when
// report_errors is enabled, so this file is a no-op on a default install.
//
// Runs inside phoenix_hook_event()'s scope: the array $context is the only
// input, and its keys depend on the source. Common keys:
//   'throwable' => \Throwable   the original exception (model writes / uncaught)
//   'message'   => string       a text message (tracker_error / fatal shutdown)
//   'level'     => string       'error' | 'fatal'
//   'source'    => string       e.g. 'peer_insert', 'torrent_add', 'tracker_error', 'php_error', 'shutdown'
//   'errno'     => int          the PHP error level (source 'php_error')
//   'file','line'               present for 'php_error' and fatal shutdown errors
//
// phoenix_hook_event() swallows anything this file throws (so a broken reporter
// can never take down the request) and blocks re-entrant events, but keep the
// handler cheap and non-blocking anyway: on the announce hot path, defer any
// network send (e.g. a Sentry flush) to shutdown via fastcgi_finish_request().
//
// Ships wired for Sentry, and inert without it: phoenix.init.php only
// initialises the SDK when sentry_dsn is set, and with no client the capture
// calls below are no-ops. Nothing here needs its own DSN check.

if (class_exists(\Sentry\SentrySdk::class) && \Sentry\SentrySdk::getCurrentHub()->getClient() !== null) {
    // Source and level as tags, so the announce hot path can be told apart from
    // an admin action at a glance, and a fatal from a handled failure.
    \Sentry\configureScope(static function (\Sentry\State\Scope $scope) use ($context): void {
        if (isset($context['source']) && is_string($context['source'])) {
            $scope->setTag('phoenix.source', $context['source']);
        }
        foreach (['file', 'line', 'errno'] as $key) {
            if (isset($context[$key]) && (is_string($context[$key]) || is_int($context[$key]))) {
                $scope->setExtra('phoenix.'.$key, $context[$key]);
            }
        }
    });

    $level = (($context['level'] ?? '') === 'fatal') ? \Sentry\Severity::fatal() : \Sentry\Severity::error();

    if (isset($context['throwable']) && $context['throwable'] instanceof \Throwable) {
        \Sentry\captureException($context['throwable']);
    } elseif (isset($context['message']) && is_string($context['message'])) {
        \Sentry\captureMessage($context['message'], $level);
    }
}
