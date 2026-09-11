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
// Ships wired for Sentry, and inert without it: the SDK is a suggested package
// and sentry_dsn defaults to empty, so both guards below fail on a default
// install and this file does nothing. Set sentry_dsn in phoenix.custom.php to
// turn it on. Any other monitor goes here the same way.

$sentry_settings = is_array($context['settings'] ?? null) ? $context['settings'] : [];
$sentry_dsn = is_string($sentry_settings['sentry_dsn'] ?? null) ? $sentry_settings['sentry_dsn'] : '';

if ($sentry_dsn !== '' && class_exists(\Sentry\SentrySdk::class)) {
    // Guarded for persistent workers: under php-fpm the bootstrap runs per
    // request and there is no client yet, but a Swoole/RoadRunner worker would
    // otherwise re-init the SDK on every request it serves.
    if (\Sentry\SentrySdk::getCurrentHub()->getClient() === null) {
        $sentry_release = is_string($sentry_settings['phoenix_version'] ?? null)
            ? $sentry_settings['phoenix_version']
            : null;

        \Sentry\init([
            'dsn' => $sentry_dsn,
            'environment' => is_string($sentry_settings['sentry_environment'] ?? null)
                ? $sentry_settings['sentry_environment']
                : 'production',
            'release' => $sentry_release,
            'traces_sample_rate' => (float) ($sentry_settings['sentry_traces_sample_rate'] ?? 0.0),
            'profiles_sample_rate' => (float) ($sentry_settings['sentry_profiles_sample_rate'] ?? 0.0),
            'enable_logs' => (bool) ($sentry_settings['sentry_enable_logs'] ?? false),
        ]);
    }
}
