<?php

declare(strict_types=1);

$settings['phoenix_version'] = 'Phoenix Procedural v.3.1 2016-04-14 15:42:00Z eustasy';

////	Error Level
// error_reporting(E_ALL);
// error_reporting(E_ALL & ~E_WARNING);
// error_reporting(E_ALL | E_STRICT | E_DEPRECATED);
error_reporting(0);

// Ignore Disconnects
ignore_user_abort(true);
ini_set('default_charset', 'iso-8859-1');

$time = time();
$chance = mt_rand(1, 100);

// Allow Access from Anywhere
header('Access-Control-Allow-Origin: *');

// Override the default database variables with this.
include __DIR__.'/../config/phoenix.default.php';
if (is_readable(__DIR__.'/../config/phoenix.custom.php')) {
	include __DIR__.'/../config/phoenix.custom.php';
} else {
	error_log('Configuration file "'.__DIR__.'/../config/phoenix.custom.php" not readable.'.PHP_EOL.
		'Falling back to defaults.');
	$settings['db_host'] = 'localhost';
	$settings['db_user'] = 'root';
	$settings['db_pass'] = 'Password1';
	$settings['db_name'] = 'phoenix';
	$settings['db_persist'] = true;
	$settings['open_tracker'] = true;
}

require_once __DIR__.'/functions/function.tracker.error.php';

////	Database Connection (inlined from once.db.connect.php)

require_once __DIR__.'/functions/function.db.is.configured.php';
if (!db_is_configured($settings)) {
	tracker_error('Connection Failed. Tracker is not configured.');
}

require_once __DIR__.'/functions/function.db.persist.host.php';
$settings['db_host'] = db_persist_host($settings['db_host'], (bool)$settings['db_persist']);

// PHP 8.1+ mysqli_report defaults to throwing on failure, so mysqli_connect()
// no longer just returns false on bad credentials. Wrap in try/catch so the
// failure path emits a friendly bencode error rather than crashing with an
// uncaught exception.
try {
	$connection = @mysqli_connect($settings['db_host'], $settings['db_user'], $settings['db_pass'], $settings['db_name']);
} catch (mysqli_sql_exception $e) {
	$connection = false;
}

if (!$connection) {
	tracker_error('Connection Failed. Tracker may be mis-configured. '.mysqli_connect_error());
}

////	Load allowed torrents for closed tracker

if (!$settings['open_tracker']) {
	require_once __DIR__.'/model/torrents.select.allowed.php';
	$allowed_torrents = torrents_select_allowed($connection, $settings);
}
