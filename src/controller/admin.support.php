<?php

declare(strict_types=1);

////	admin_support_controller
// Renders the admin Server Support page: PHP/MySQL/extension diagnostics, the
// installed-tables check, the current database size, and the state of the four
// optional extras. Read-only — there are no forms, so no CSRF or action
// dispatch (the only token built is for the layout's logout form). Dispatched
// by admin_panel_controller() for page=support.
//
// The extras are gathered here rather than in the view so the view stays a pure
// function of its arguments and the tests can reach every branch without
// installing or uninstalling anything.

/** @param PhoenixSettings $settings */
function admin_support_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../model/db.tables.installed.php';
    $tables_installed = db_tables_installed($connection, $settings);

    $database_size = false;
    if ($tables_installed) {
        require_once __DIR__.'/../model/db.size.php';
        $database_size = db_size($connection, $settings);
    }

    ////	Optional extras
    // Each is off by default and none is required to run a tracker, so the page
    // reports what IS configured rather than demanding anything — except where
    // a setting asks for something the server cannot deliver, which is a real
    // fault and says so.
    $geo_database = $settings['stats_geo_database'];
    $extras = [
        'geo' => [
            'enabled' => $settings['stats_geo'] === true,
            // The pure-PHP reader walks the mmdb's search tree in userland and
            // is roughly 40x slower than the C extension; on the announce path
            // that is the difference between microseconds and half a
            // millisecond per peer, so which one is loaded is worth stating.
            'reader' => class_exists(\MaxMind\Db\Reader::class)
                ? (extension_loaded('maxminddb') ? 'extension' : 'php')
                : 'missing',
            'database' => $geo_database,
            'readable' => $geo_database !== '' && is_readable($geo_database),
        ],
        'sentry' => [
            'installed' => class_exists(\Sentry\SentrySdk::class),
            'dsn' => $settings['sentry_dsn'] !== '',
            'reporting' => $settings['report_errors'] === true,
        ],
        'totp' => [
            'installed' => class_exists(\eustasy\Authenticatron::class),
            'enabled' => $settings['admin_totp_secret'] !== '',
            // Without a password there is no login to add a second factor to.
            'password' => ! empty($settings['admin_password']),
        ],
        'backups' => [
            'requested' => ! empty($settings['backup_compress']),
            // db_backup() gates on gzopen() specifically, so test what it tests.
            'available' => function_exists('gzopen'),
        ],
    ];

    // Token only for the layout's logout form; this page has no forms of its own.
    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.support.php';

    return view_admin_support_html($settings, $tables_installed, $database_size, $csrf_token, null, null, $extras);
}
