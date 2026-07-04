<?php

declare(strict_types=1);

////	Init
// Fired once at the start of every request from phoenix.php, but only when
// $settings['report_errors'] is on — so this file is a no-op on a default
// install. It runs after the Composer autoload and settings load, and BEFORE
// the DB connects: the place to initialise an external monitor (e.g. Sentry) and
// attach request-wide context, so even a DB-connect failure is captured within
// an initialised scope.
//
// Runs inside phoenix_hook_event()'s scope: the array $context is the only
// input. $context['settings'] is the full loaded settings array (read
// 'phoenix_version' for a release tag, etc.). $connection is NOT available (the
// DB is not up yet) and must not be used here.
//
// Under php-fpm / php -S the bootstrap runs per request, so this fires per
// request — the standard PHP model (a fresh scope + breadcrumbs each request).
// Under a persistent worker (Swoole/RoadRunner) guard the one-time SDK init and
// only reset scope per request, e.g.:
//   if (! \Sentry\SentrySdk::getCurrentHub()->getClient()) { \Sentry\init([...]); }
//
// phoenix_hook_event() swallows anything this file throws, so a bad DSN degrades
// to "no reporting", never a broken tracker.
//
// This shipped file is an empty placeholder. Example Sentry init — add
// sentry/sentry via Composer, then replace this file's body with:
//
//   if (class_exists(\Sentry\SentrySdk::class)) {
//       $release = is_array($context['settings'] ?? null) ? ($context['settings']['phoenix_version'] ?? null) : null;
//       \Sentry\init([
//           'dsn'         => 'https://…',
//           'environment' => 'production',
//           'release'     => $release,
//       ]);
//   }
