<?php

$settings['phoenix_version'] = 'Phoenix Procedural v.3.1 2016-04-14 15:42:00Z eustasy';

// If the root isn't a directory up, modify that here.
$settings['root'] = __DIR__.'/';
// Don't modify these, they'll figure it out.
$settings['functions'] = $settings['root'].'_functions/phoenix/';
$settings['hooks']     = $settings['root'].'_hooks/phoenix/';
$settings['onces']     = $settings['root'].'_onces/phoenix/';
$settings['settings']  = $settings['root'].'_settings/';

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
include $settings['settings'].'phoenix.default.php';
if ( is_readable($settings['settings'].'phoenix.custom.php') ) {
	include $settings['settings'].'phoenix.custom.php';
} else {
	error_log('Configuration file "'.$settings['settings'].'phoenix.custom.php" not readable.'.PHP_EOL.
		'Falling back to defaults.');
	$settings['db_host'] = 'localhost';
	$settings['db_user'] = 'root';
	$settings['db_pass'] = 'Password1';
	$settings['db_name'] = 'phoenix';
	$settings['db_persist'] = true;
	$settings['open_tracker'] = true;
}

require_once $settings['functions'].'function.tracker.error.php';
// DB_Connect must be loaded after tracker_error and settings.
require_once $settings['onces'].'once.db.connect.php';

if ( !$settings['open_tracker'] ) {
	require_once $settings['functions'].'function.tracker.allowed.php';
	$allowed_torrents = tracker_allowed($connection, $settings);
}
