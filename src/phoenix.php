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
$settings['phoenix_version'] = 'v4.3beta8';

////	Resolve the GeoLite2 database
// When geo enrichment is on, resolve the database from standard locations
// (/usr/share/GeoIP, /var/lib/GeoIP, config/) so an explicit path is optional.
// Only when stats_geo is set, to keep the announce hot path free of file stats.
if ($settings['stats_geo']) {
    require_once __DIR__.'/functions/stats.geo.database.php';
    $settings['stats_geo_database'] = stats_geo_database($settings);
}

////	Apply operator error settings (debug / error_log)
require_once __DIR__.'/functions/error.configure.php';
error_configure($settings);

require_once __DIR__.'/functions/tracker.error.php';

////	Error reporting hooks (opt-in)
// When report_errors is on: fire the 'init' event so an operator can initialise
// their monitor (e.g. \Sentry\init()) and attach request context up front, then
// register handlers that route uncaught exceptions + fatal shutdown errors
// through phoenix_hook_event('error', ...). Both run after the autoload/settings
// load and BEFORE the DB connect below, so even a connection failure lands in an
// initialised context. Off by default = nothing registered, identical to before.
if ($settings['report_errors']) {
    require_once __DIR__.'/functions/phoenix.hook.event.php';
    phoenix_hook_event('init', ['settings' => $settings]);

    require_once __DIR__.'/functions/error.handle.register.php';
    error_handle_register();
}

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
    // A live tracker losing its DB is a server fault worth reporting; the hook
    // dispatch does not need the DB, so it works even here.
    tracker_error('Connection Failed. Tracker may be mis-configured. '.mysqli_connect_error(), null, $settings['report_errors']);
}

////	Load allowed torrents for closed tracker (BEP 27)
// Closed-tracker mode is the tracker-side half of BEP 27 (private torrents):
// only info_hashes registered here may be announced to or scraped.

if (! $settings['open_tracker']) {
    require_once __DIR__.'/model/torrents.select.allowed.php';
    $allowed_torrents = torrents_select_allowed($connection, $settings);
}
