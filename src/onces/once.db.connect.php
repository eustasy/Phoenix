<?php

require_once $settings['functions'].'function.db.is.configured.php';
if ( !db_is_configured($settings) ) {
	tracker_error('Connection Failed. Tracker is not configured.');
}

require_once $settings['functions'].'function.db.persist.host.php';
$settings['db_host'] = db_persist_host($settings['db_host'], (bool)$settings['db_persist']);

// PHP 8.1+ mysqli_report defaults to throwing on failure, so mysqli_connect()
// no longer just returns false on bad credentials. Wrap in try/catch so the
// failure path emits a friendly bencode error rather than crashing with an
// uncaught exception. mysqli_connect_error() takes no arguments.
try {
	$connection = @mysqli_connect($settings['db_host'], $settings['db_user'], $settings['db_pass'], $settings['db_name']);
} catch ( mysqli_sql_exception $e ) {
	$connection = false;
}

if ( !$connection ) {
	tracker_error('Connection Failed. Tracker may be mis-configured. '.mysqli_connect_error());
}
