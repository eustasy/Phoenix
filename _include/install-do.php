<?php

if (
	$settings_writable &&
	isset($_POST['process']) &&
	$_POST['process'] === 'install'
) {
	$db_host      = !empty($_POST['db_host'])   ? strip_tags($_POST['db_host'])                              : 'localhost';
	$db_user      = !empty($_POST['db_user'])   ? strip_tags($_POST['db_user'])                              : '';
	$db_pass      =  isset($_POST['db_pass'])   ? $_POST['db_pass']                                         : '';
	// db_name and db_prefix are used as SQL identifiers (backtick-quoted); restrict to safe chars.
	$db_name      = !empty($_POST['db_name'])   ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_name'])   : 'phoenix';
	$db_prefix    = !empty($_POST['db_prefix']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_prefix']) : '';
	$db_persist      = !empty($_POST['db_persist']);
	$open_tracker    = !empty($_POST['open_tracker']);
	$public_index    = !empty($_POST['public_index']);
	$admin_password  = isset($_POST['admin_password']) && $_POST['admin_password'] !== ''
		? password_hash($_POST['admin_password'], PASSWORD_DEFAULT)
		: '';

	// Test the connection before writing anything.
	$test_host = $db_persist ? 'p:'.$db_host : $db_host;
	$test_conn = @mysqli_connect($test_host, $db_user, $db_pass, $db_name);

	if ( !$test_conn ) {
		$install_error = 'Could not connect to the database: '.mysqli_connect_error();
	} else {
		$settings['db_prefix'] = $db_prefix;
		$settings['db_name']   = $db_name;
		require_once $settings['functions'].'function.mysqli.create.database.php';
		if ( !create_database($test_conn, $settings) ) {
			$install_error = 'Connected, but could not create the tables.';
		} else {
			$s = function($v) { return '\''.addslashes($v).'\''; };
			$b = function($v) { return $v ? 'true' : 'false'; };
			$config  = '<?php'.PHP_EOL.PHP_EOL;
			$config .= '$settings[\'db_host\']      = '.$s($db_host).';'.PHP_EOL;
			$config .= '$settings[\'db_user\']      = '.$s($db_user).';'.PHP_EOL;
			$config .= '$settings[\'db_pass\']      = '.$s($db_pass).';'.PHP_EOL;
			$config .= '$settings[\'db_name\']      = '.$s($db_name).';'.PHP_EOL;
			$config .= '$settings[\'db_prefix\']    = '.$s($db_prefix).';'.PHP_EOL;
			$config .= '$settings[\'db_persist\']   = '.$b($db_persist).';'.PHP_EOL;
			$config .= '$settings[\'db_reset\']     = false;'.PHP_EOL;
			$config .= '$settings[\'open_tracker\']    = '.$b($open_tracker).';'.PHP_EOL;
			$config .= '$settings[\'public_index\']    = '.$b($public_index).';'.PHP_EOL;
			$config .= '$settings[\'admin_password\']  = '.$s($admin_password).';'.PHP_EOL;
			if ( file_put_contents($config_path, $config) !== false ) {
				header('Location: admin.php?installed=1');
				exit;
			} else {
				$install_error = 'Connected and created tables, but could not write the configuration file. Check that <code>_settings/</code> is writable.';
			}
		}
		mysqli_close($test_conn);
	}
}
