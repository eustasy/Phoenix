<?php

// This page is not secure.
// It should not be deployed in a production environment.

// Bootstrap paths before attempting a DB connection.
$settings['root']      = __DIR__.'/';
$settings['functions'] = $settings['root'].'src/functions/';
$settings['hooks']     = $settings['root'].'src/hooks/';
$settings['onces']     = $settings['root'].'src/onces/';
$settings['settings']  = $settings['root'].'config/';

require_once $settings['functions'].'function.tracker.error.php';

$config_path   = $settings['settings'].'phoenix.custom.php';
$config_exists = is_readable($config_path);

////	Installation Flow
// Runs when no configuration file exists.
if ( !$config_exists ) {

	error_reporting(0);

	$settings_writable = is_writable($settings['settings']);
	$install_error     = null;

	require __DIR__.'/src/onces/once.install.php';
	require __DIR__.'/src/includes/install-form.php';
	// install-form.php calls exit.

}

////	Normal Admin Flow
require_once __DIR__.'/_phoenix.php';
require_once $settings['onces'].'once.auth.php';
require_once $settings['onces'].'once.sanitize.admin.php';
require_once $settings['functions'].'function.mysqli.drop.table.php';
require_once $settings['functions'].'function.mysqli.create.database.php';

// Tables Exist
$tables = array('peers', 'tasks', 'torrents');
$actual = 0;
foreach ( $tables as $table ) {
	$sql = 'SELECT TABLE_NAME '.
	'FROM `information_schema`.`TABLES` '.
	'WHERE TABLE_SCHEMA = \''.$settings['db_name'].'\' '.
	'AND TABLE_NAME = \''.$settings['db_prefix'].$table.'\';';

	$result = mysqli_query($connection, $sql);
	$count = mysqli_num_rows($result);
	if ( $count ) {
		$actual += $count;
	}
}
if ( count($tables) == $actual ) {
	$tables_installed = true;
} else {
	$tables_installed = false;
}

if (
	$Process == 'setup' &&
	(
		$settings['db_reset'] ||
		!$tables_installed
	)
) {
	// MySQL Setup
	$success = true;

	if ( $tables_installed ) {
		if (
			!drop_table($connection, $settings, 'peers') ||
			!drop_table($connection, $settings, 'tasks') ||
			!drop_table($connection, $settings, 'torrents')
		) {
			$success = false;
		}
	}

	// Create the database tables.
	if ( !create_database($connection, $settings) ) {
		$success = false;
	}

	if ( $success ) {
		$Message = 'Your MySQL Tracker Database has been setup.';
		require_once $settings['functions'].'function.task.log.php';
		$result = task_log($connection, $settings, 'install', $time);
		$tables_installed = true;
	} else {
		$Message = 'Could not setup the MySQL Database.';
	}

} else if ( $Process == 'clean' ) {
	require_once $settings['functions'].'function.task.clean.php';
	if ( task_clean($connection, $settings, $time) ) {
		$Message = 'The peers list has been cleaned.';
	} else {
		$Message = 'Could not clean the peers list.';
	}

} else if ( $Process == 'optimize' ) {
	require_once $settings['functions'].'function.task.optimize.php';
	if ( task_optimize($connection, $settings, $time) ) {
		$Message = 'Your MySQL Tracker Database has been optimized.';
	} else {
		$Message = 'Could not optimize the MySQL Database.';
	}
}

require __DIR__.'/src/includes/admin-panel.php';
