<?php

////	admin_install_controller
//  Handles first-run installer mode when no config file exists.
//  Returns HTML output.

function admin_install_controller($settings, $config_path) {
	error_reporting(0);

	$settings_writable = is_writable($settings['settings']);
	$install_error     = null;

	////	Process installation

	if (
		$settings_writable &&
		isset($_POST['process']) &&
		$_POST['process'] === 'install'
	) {
		require_once $settings['functions'].'function.install.sanitize.post.php';
		$values = install_sanitize_post($_POST);

		////	Test DB connection before writing config

		$test_host = $values['db_persist'] ? 'p:'.$values['db_host'] : $values['db_host'];
		try {
			$test_conn = @mysqli_connect($test_host, $values['db_user'], $values['db_pass'], $values['db_name']);
		} catch (mysqli_sql_exception $e) {
			$test_conn = false;
		}

		if (!$test_conn) {
			$install_error = 'Could not connect to the database: '.mysqli_connect_error();
		} else {
			////	Create tables

			$settings['db_prefix'] = $values['db_prefix'];
			$settings['db_name']   = $values['db_name'];
			require_once $settings['model'].'db.create.php';
			if (!create_database($test_conn, $settings)) {
				$install_error = 'Connected, but could not create the tables.';
			} else {
				////	Write config file

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

	////	Prepare form values (repopulate after failed attempt)

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
	return view_install_html($settings_writable, $install_error, $form);
}
