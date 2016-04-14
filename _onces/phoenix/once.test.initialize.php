<?php

////	Settings
// Import the settings.
require_once __DIR__.'/../../_settings/phoenix.default.php';
if ( is_readable(__DIR__.'/../../_settings/phoenix.custom.php') ) {
	require_once __DIR__.'/../../_settings/phoenix.custom.php';
}
// Over-ride the DB_Reset setting.
$settings['db_reset'] = true;
// Over-ride the DB_Prefix setting.
$settings['db_prefix'] = $settings['db_prefix'].'TESTING_';

if ( getenv('CI') ) {
	$root_db = mysqli_connect('localhost', 'root', '', $settings['db_name']);
	if ( !$root_db ) {
		echo 'Failed to connect to database for testing.';
		$failure = true;
	}
	$query_grant = 'GRANT ALL ON `'.$settings['db_name'].'`.* TO \''.$settings['db_user'].'\'@\''.$settings['db_host'].'\' IDENTIFIED BY \''.$settings['db_pass'].'\';';
	$query_flush = 'FLUSH PRIVILEGES;';
	mysqli_query($root_db, $query_grant);
	mysqli_query($root_db, $query_flush);
}

// Connect to the Database for testing.
$test_db = mysqli_connect($settings['db_host'], $settings['db_user'], $settings['db_pass'], $settings['db_name']);
if ( !$test_db ) {
	echo 'Failed to connect to database for testing.';
	$failure = true;
}

require_once __DIR__.'/../../_functions/phoenix/function.mysqli.create.database.php';
$failure = !create_database($test_db, $settings);

if ( !empty($failure) ) {
	exit(1);
}
