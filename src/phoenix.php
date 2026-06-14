<?php

declare(strict_types=1);

////	Error Handling
// Pre-settings baseline: log errors, never display them. Tracker responses are
// bencode/binary, so a printed warning corrupts the body and discloses
// internals. Set before anything else so failures during bootstrap are logged;
// error_configure() layers the operator's debug/error_log overrides once
// $settings is loaded.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

// Ignore Disconnects
ignore_user_abort(true);
ini_set('default_charset', 'iso-8859-1');

// Composer autoloader for optional libraries (geoip2 stat-tracking enrichment,
// authenticatron admin 2FA). Conditional: installs that don't use Composer, or
// that run before `composer install`, still bootstrap — the features that need
// these classes guard with class_exists().
$phoenix_autoload = __DIR__.'/../vendor/autoload.php';
if (is_readable($phoenix_autoload)) {
    require_once $phoenix_autoload;
}

$time = time();

////	Settings
require_once __DIR__.'/functions/settings.load.php';
$settings = settings_load(
    __DIR__.'/../config/phoenix.default.php',
    __DIR__.'/../config/phoenix.custom.php',
);
$settings['phoenix_version'] = 'Phoenix v4.2beta5 2026-06-14 11:04:15Z eustasy';

////	Apply operator error settings (debug / error_log)
require_once __DIR__.'/functions/error.configure.php';
error_configure($settings);

require_once __DIR__.'/functions/tracker.error.php';

////	Database Connection
require_once __DIR__.'/functions/db.is.configured.php';
if (! db_is_configured($settings)) {
    tracker_error('Connection Failed. Tracker is not configured.');
}

require_once __DIR__.'/functions/db.persist.host.php';
$settings['db_host'] = db_persist_host($settings['db_host'], (bool)$settings['db_persist']);

require_once __DIR__.'/functions/db.connect.php';
$connection = db_connect($settings);
if (! $connection) {
    tracker_error('Connection Failed. Tracker may be mis-configured. '.mysqli_connect_error());
}

////	Load allowed torrents for closed tracker

if (! $settings['open_tracker']) {
    require_once __DIR__.'/model/torrents.select.allowed.php';
    $allowed_torrents = torrents_select_allowed($connection, $settings);
}
