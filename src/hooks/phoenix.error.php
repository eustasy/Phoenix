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
// This shipped file is an empty placeholder. Example Sentry wiring — add
// sentry/sentry via Composer, initialise the SDK in src/hooks/phoenix.init.php,
// then replace this file's body with:
//
//   if (class_exists(\Sentry\SentrySdk::class)) {
//       if (isset($context['throwable']) && $context['throwable'] instanceof \Throwable) {
//           \Sentry\captureException($context['throwable']);
//       } elseif (isset($context['message']) && is_string($context['message'])) {
//           \Sentry\captureMessage($context['message']);
//       }
//   }
