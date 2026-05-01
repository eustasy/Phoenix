<?php

// This page is not secure.
// It should not be deployed in a production environment.

// Bootstrap paths before attempting a DB connection.
$settings['root']      = __DIR__.'/../';
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

	require $settings['onces'].'once.install.php';
	
	// Prepare form values (repopulate after failed attempt)
	$form = array(
		'db_host'      => isset($_POST['db_host'])      ? $_POST['db_host']   : 'localhost',
		'db_user'      => isset($_POST['db_user'])      ? $_POST['db_user']   : '',
		'db_name'      => isset($_POST['db_name'])      ? $_POST['db_name']   : 'phoenix',
		'db_prefix'    => isset($_POST['db_prefix'])    ? $_POST['db_prefix'] : 'phoenix_',
		'db_persist'   => !isset($_POST['db_persist'])  || $_POST['db_persist'],
		'open_tracker' => isset($_POST['open_tracker']) && $_POST['open_tracker'],
		'public_index' => isset($_POST['public_index']) && $_POST['public_index'],
	);
	
	require_once $settings['functions'].'function.tracker.error.php';
	$settings['views'] = $settings['root'].'src/views/';
	require_once $settings['views'].'html.install.php';
	echo view_install_html($settings_writable, $install_error, $form);
	exit;

}

////	Normal Admin Flow
require_once __DIR__.'/../src/phoenix.php';
require_once $settings['onces'].'once.auth.php';
require_once $settings['model'].'db.drop.php';
require_once $settings['model'].'db.create.php';

////	Process Form
$process = false;
if ( !empty($_POST['process']) ) {
	$process = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
}

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
	$process == 'setup' &&
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
		require_once $settings['model'].'task.log.php';
		$result = task_log($connection, $settings, 'install', $time);
		$tables_installed = true;
	} else {
		$Message = 'Could not setup the MySQL Database.';
	}

} else if ( $process == 'clean' ) {
	require_once $settings['functions'].'function.task.clean.php';
	if ( task_clean($connection, $settings, $time) ) {
		$Message = 'The peers list has been cleaned.';
	} else {
		$Message = 'Could not clean the peers list.';
	}

} else if ( $process == 'optimize' ) {
	require_once $settings['functions'].'function.task.optimize.php';
	if ( task_optimize($connection, $settings, $time) ) {
		$Message = 'Your MySQL Tracker Database has been optimized.';
	} else {
		$Message = 'Could not optimize the MySQL Database.';
	}
}

// Calculate database size if tables are installed
$database_size = false;
if ( $tables_installed ) {
	$database_size_query = 'SELECT `data_length` AS `Data`, `index_length` AS `Indexes`, SUM( `data_length` + `index_length` ) AS `Total`, SUM( `data_free` ) AS `Free` FROM `information_schema`.`TABLES` WHERE `table_schema` = \''.$settings['db_name'].'\' GROUP BY `table_schema`;';
	$result = mysqli_query($connection, $database_size_query, MYSQLI_STORE_RESULT);
	if ( $result ) {
		$database_size = mysqli_fetch_assoc($result);
	}
}

require_once $settings['views'].'html.admin.php';
echo view_admin_html(
	$settings,
	$tables_installed,
	$database_size,
	$Message ?? false,
	isset($_GET['installed'])
);
