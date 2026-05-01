<?php

////	Admin Panel & Installer
// This page is not secure.
// It should not be deployed in a production environment.

////	Bootstrap paths before attempting DB connection

$settings['root']      = __DIR__.'/../';
$settings['functions'] = $settings['root'].'src/functions/';
$settings['hooks']     = $settings['root'].'src/hooks/';
$settings['model']     = $settings['root'].'src/model/';
$settings['onces']     = $settings['root'].'src/onces/';
$settings['settings']  = $settings['root'].'config/';
$settings['views']     = $settings['root'].'src/views/';

require_once $settings['functions'].'function.tracker.error.php';

$config_path   = $settings['settings'].'phoenix.custom.php';
$config_exists = is_readable($config_path);

////	Installer Mode (no config file exists)

if (!$config_exists) {
	error_reporting(0);

	$settings_writable = is_writable($settings['settings']);
	$install_error     = null;

	// Inlined from once.install.php
	if (
		$settings_writable &&
		isset($_POST['process']) &&
		$_POST['process'] === 'install'
	) {
		require_once $settings['functions'].'function.install.sanitize.post.php';
		$values = install_sanitize_post($_POST);

		// Test DB connection before writing config
		$test_host = $values['db_persist'] ? 'p:'.$values['db_host'] : $values['db_host'];
		try {
			$test_conn = @mysqli_connect($test_host, $values['db_user'], $values['db_pass'], $values['db_name']);
		} catch (mysqli_sql_exception $e) {
			$test_conn = false;
		}

		if (!$test_conn) {
			$install_error = 'Could not connect to the database: '.mysqli_connect_error();
		} else {
			// Create tables
			$settings['db_prefix'] = $values['db_prefix'];
			$settings['db_name']   = $values['db_name'];
			require_once $settings['model'].'db.create.php';
			if (!create_database($test_conn, $settings)) {
				$install_error = 'Connected, but could not create the tables.';
			} else {
				// Write config file
				require_once $settings['functions'].'function.install.build.config.php';
				if (file_put_contents($config_path, install_build_config($values)) === false) {
					$install_error = 'Connected and created tables, but could not write the configuration file. Check that <code>config/</code> is writable.';
				} else {
					mysqli_close($test_conn);
					header('Location: admin.php?installed=1');
					exit;
				}
			}
			mysqli_close($test_conn);
		}
	}

	// Prepare form values (repopulate after failed attempt)
	$form = array(
		'db_host'      => $_POST['db_host']   ?? 'localhost',
		'db_user'      => $_POST['db_user']   ?? '',
		'db_name'      => $_POST['db_name']   ?? 'phoenix',
		'db_prefix'    => $_POST['db_prefix'] ?? 'phoenix_',
		'db_persist'   => !isset($_POST['db_persist'])  || $_POST['db_persist'],
		'open_tracker' => isset($_POST['open_tracker']) && $_POST['open_tracker'],
		'public_index' => isset($_POST['public_index']) && $_POST['public_index'],
	);

	require_once $settings['views'].'html.install.php';
	echo view_install_html($settings_writable, $install_error, $form);
	exit;
}

////	Normal Admin Mode (full bootstrap)

require_once __DIR__.'/../src/phoenix.php';

////	Authentication (inlined from once.auth.php)

if (!empty($settings['admin_password'])) {
	session_start();

	require_once $settings['functions'].'function.auth.handle.logout.php';
	auth_handle_logout();

	require_once $settings['functions'].'function.auth.is.authenticated.php';
	if (!auth_is_authenticated()) {
		$login_error = isset($_POST['process']) && $_POST['process'] === 'login';

		if ($login_error) {
			require_once $settings['functions'].'function.auth.verify.login.php';
			if (auth_verify_login($settings)) {
				require_once $settings['functions'].'function.auth.set.authenticated.php';
				auth_set_authenticated();
				header('Location: '.$_SERVER['REQUEST_URI']);
				exit;
			}
		}

		require_once $settings['views'].'html.login.php';
		echo view_login_html($login_error);
		exit;
	}
}

////	Process Actions

require_once $settings['model'].'db.drop.php';
require_once $settings['model'].'db.create.php';

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

////	Setup Action

if (
	$process == 'setup' &&
	(
		$settings['db_reset'] ||
		!$tables_installed
	)
) {
	$success = true;

	if ($tables_installed) {
		if (
			!drop_table($connection, $settings, 'peers') ||
			!drop_table($connection, $settings, 'tasks') ||
			!drop_table($connection, $settings, 'torrents')
		) {
			$success = false;
		}
	}

	if (!create_database($connection, $settings)) {
		$success = false;
	}

	if ($success) {
		$Message = 'Your MySQL Tracker Database has been setup.';
		require_once $settings['model'].'task.log.php';
		task_log($connection, $settings, 'install', $time);
		$tables_installed = true;
	} else {
		$Message = 'Could not setup the MySQL Database.';
	}

////	Clean Action

} elseif ($process == 'clean') {
	require_once $settings['functions'].'function.task.clean.php';
	if (task_clean($connection, $settings, $time)) {
		$Message = 'The peers list has been cleaned.';
	} else {
		$Message = 'Could not clean the peers list.';
	}

////	Optimize Action

} elseif ($process == 'optimize') {
	require_once $settings['model'].'db.optimize.php';
	if (task_optimize($connection, $settings, $time)) {
		$Message = 'Your MySQL Tracker Database has been optimized.';
	} else {
		$Message = 'Could not optimize the MySQL Database.';
	}
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
