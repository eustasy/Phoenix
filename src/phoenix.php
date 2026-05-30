<?php

declare(strict_types=1);

////	Error Level
// error_reporting(E_ALL);
// error_reporting(E_ALL & ~E_WARNING);
// error_reporting(E_ALL | E_STRICT | E_DEPRECATED);
error_reporting(0);

// Ignore Disconnects
ignore_user_abort(true);
ini_set('default_charset', 'iso-8859-1');

$time = time();

// Allow Access from Anywhere
header('Access-Control-Allow-Origin: *');

////	Settings
require_once __DIR__.'/functions/settings.load.php';
$settings = settings_load(
	__DIR__.'/../config/phoenix.default.php',
	__DIR__.'/../config/phoenix.custom.php'
);
$settings['phoenix_version'] = 'Phoenix Procedural v4.0beta2 2026-05-09 17:23:00Z eustasy';

require_once __DIR__.'/functions/tracker.error.php';

////	Database Connection
require_once __DIR__.'/functions/db.is.configured.php';
if (!db_is_configured($settings)) {
	tracker_error('Connection Failed. Tracker is not configured.');
}

require_once __DIR__.'/functions/db.persist.host.php';
$settings['db_host'] = db_persist_host($settings['db_host'], (bool)$settings['db_persist']);

require_once __DIR__.'/functions/db.connect.php';
$connection = db_connect($settings);
if (!$connection) {
	tracker_error('Connection Failed. Tracker may be mis-configured. '.mysqli_connect_error());
}

////	Load allowed torrents for closed tracker

if (!$settings['open_tracker']) {
	require_once __DIR__.'/model/torrents.select.allowed.php';
	$allowed_torrents = torrents_select_allowed($connection, $settings);
}
