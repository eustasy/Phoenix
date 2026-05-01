<?php

////	admin_install_controller
//  Handles first-run installer mode when no config file exists.
//  Returns HTML output.

require_once $settings['views'].'html.install.php';

function admin_install_controller($settings, $config_path) {
	error_reporting(0);

	require_once $settings['functions'].'function.install.sanitize.post.php';
	$values = install_sanitize_post($_POST);

	////	Prepare form values (repopulate after failed attempt)
	$form = array(
		'db_host'      => $values['db_host']   ?? 'localhost',
		'db_user'      => $values['db_user']   ?? '',
		'db_name'      => $values['db_name']   ?? 'phoenix',
		'db_prefix'    => $values['db_prefix'] ?? 'phoenix_',
		'db_persist'   => !isset($values['db_persist'])  || $values['db_persist'],
		'open_tracker' => isset($values['open_tracker']) && $values['open_tracker'],
		'public_index' => isset($values['public_index']) && $values['public_index'],
	);


	////	Process installation
	if (!isset($values['process']) || $values['process'] !== 'install') {
		// Not attempting installation, just show the form
		return view_install_html($settings_writable, $install_error, $form);
	}

	$install_error     = null;
	$settings_writable = is_writable($settings['settings']);
	if ( !$settings_writable ) {
		$install_error = 'The <code>config/</code> directory is not writable. Please make it writable and try again.';
		return view_install_html($settings_writable, $install_error, $form);
	}

	////	Test DB connection before writing config
	$test_host  = $values['db_persist'] ? 'p:': '';
	$test_host .= $values['db_host'];
	try {
		$test_conn = @mysqli_connect($test_host, $values['db_user'], $values['db_pass'], $values['db_name']);
	} catch (mysqli_sql_exception $e) {
		$test_conn = false;
	}

	if (!$test_conn) {
		$install_error = 'Could not connect to the database: '.mysqli_connect_error();
		return view_install_html($settings_writable, $install_error, $form);
	}

	////	Create tables
	$settings['db_prefix'] = $values['db_prefix'];
	$settings['db_name']   = $values['db_name'];
	require_once $settings['model'].'db.create.php';
	if (!create_database($test_conn, $settings)) {
		$install_error = 'Connected, but could not create the tables.';
		return view_install_html($settings_writable, $install_error, $form);
	}

	////	Write config file
	require_once $settings['functions'].'function.install.build.config.php';
	if (file_put_contents($config_path, install_build_config($values)) === false) {
		$install_error = 'Connected and created tables, but could not write the configuration file. Check that <code>config/</code> is writable.';
		return view_install_html($settings_writable, $install_error, $form);
	}

	mysqli_close($test_conn);
	header('Location: admin.php?installed=1');
	exit;
}
