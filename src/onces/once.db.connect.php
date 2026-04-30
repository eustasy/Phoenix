<?php

require_once $settings['functions'].'function.db.is.configured.php';
if ( !db_is_configured($settings) ) {
	tracker_error('Connection Failed. Tracker is not configured.');
}

require_once $settings['functions'].'function.db.persist.host.php';
$settings['db_host'] = db_persist_host($settings['db_host'], (bool)$settings['db_persist']);

$connection = mysqli_connect($settings['db_host'], $settings['db_user'], $settings['db_pass'], $settings['db_name']);

if ( !$connection ) {
	tracker_error('Connection Failed. Tracker may be mis-configured. '.mysqli_connect_error($connection));
}
