<?php

////	Admin Panel & Installer
// This page is not secure.
// It should not be deployed in a production environment.

////	Bootstrap paths before attempting DB connection

$settings['root']       = __DIR__.'/../';
$settings['controller'] = $settings['root'].'src/controller/';
$settings['functions']  = $settings['root'].'src/functions/';
$settings['hooks']      = $settings['root'].'src/hooks/';
$settings['model']      = $settings['root'].'src/model/';
$settings['settings']   = $settings['root'].'config/';
$settings['views']      = $settings['root'].'src/views/';

require_once $settings['functions'].'function.tracker.error.php';

$config_path   = $settings['settings'].'phoenix.custom.php';
$config_exists = is_readable($config_path);

////	Installer Mode (no config file exists)

if (!$config_exists) {
	require_once $settings['controller'].'admin.install.php';
	echo admin_install_controller($settings, $config_path);
	exit;
}

////	Normal Admin Mode (full bootstrap)

require_once __DIR__.'/../src/phoenix.php';

////	Authentication

require_once $settings['controller'].'admin.login.php';
$login_output = admin_login_controller($settings);
if ($login_output !== null) {
	echo $login_output;
	exit;
}

////	Process Actions

$process = false;
if (!empty($_POST['process'])) {
	$process = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
}

////	Check if tables exist

$tables = array('peers', 'tasks', 'torrents');
$actual = 0;
foreach ($tables as $table) {
	$sql = 'SELECT TABLE_NAME '.
		'FROM `information_schema`.`TABLES` '.
		'WHERE TABLE_SCHEMA = \''.$settings['db_name'].'\' '.
		'AND TABLE_NAME = \''.$settings['db_prefix'].$table.'\';';

	$result = mysqli_query($connection, $sql);
	$count = mysqli_num_rows($result);
	if ($count) {
		$actual += $count;
	}
}
$tables_installed = (count($tables) == $actual);

////	Dispatch actions to controllers

$Message = false;

if ($process == 'setup') {
	require_once $settings['controller'].'admin.setup.php';
	$result = admin_setup_action($connection, $settings, $time, $tables_installed);
	if ($result !== false) {
		$Message = $result;
		$tables_installed = true;
	}
} elseif ($process == 'clean') {
	require_once $settings['controller'].'admin.clean.php';
	$Message = admin_clean_action($connection, $settings, $time);
} elseif ($process == 'optimize') {
	require_once $settings['controller'].'admin.optimize.php';
	$Message = admin_optimize_action($connection, $settings, $time);
}

////	Calculate database size

$database_size = false;
if ($tables_installed) {
	$database_size_query = 'SELECT `data_length` AS `Data`, `index_length` AS `Indexes`, SUM( `data_length` + `index_length` ) AS `Total`, SUM( `data_free` ) AS `Free` FROM `information_schema`.`TABLES` WHERE `table_schema` = \''.$settings['db_name'].'\' GROUP BY `table_schema`;';
	$result = mysqli_query($connection, $database_size_query, MYSQLI_STORE_RESULT);
	if ($result) {
		$database_size = mysqli_fetch_assoc($result);
	}
}

////	Render admin panel

require_once $settings['views'].'html.admin.php';
echo view_admin_html(
	$settings,
	$tables_installed,
	$database_size,
	$Message ?? false,
	isset($_GET['installed'])
);
