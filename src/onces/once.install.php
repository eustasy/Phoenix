<?php

declare(strict_types=1);

if (
	!$settings_writable ||
	!isset($_POST['process']) ||
	$_POST['process'] !== 'install'
) {
	return;
}

require_once $settings['functions'].'function.install.sanitize.post.php';
$values = install_sanitize_post($_POST);

// Test the connection before writing anything. PHP 8.1+ mysqli_report defaults
// to throwing on errors, so the @ operator alone isn't enough — wrap in
// try/catch so a bad credential becomes a friendly $install_error rather than
// a fatal exception.
$test_host = $values['db_persist'] ? 'p:'.$values['db_host'] : $values['db_host'];
try {
	$test_conn = @mysqli_connect($test_host, $values['db_user'], $values['db_pass'], $values['db_name']);
} catch ( mysqli_sql_exception $e ) {
	$test_conn = false;
}

if ( !$test_conn ) {
	$install_error = 'Could not connect to the database: '.mysqli_connect_error();
	return;
}

$settings['db_prefix'] = $values['db_prefix'];
$settings['db_name']   = $values['db_name'];
require_once $settings['model'].'db.create.php';
if ( !create_database($test_conn, $settings) ) {
	$install_error = 'Connected, but could not create the tables.';
	mysqli_close($test_conn);
	return;
}

require_once $settings['functions'].'function.install.build.config.php';
if ( file_put_contents($config_path, install_build_config($values)) === false ) {
	$install_error = 'Connected and created tables, but could not write the configuration file. Check that <code>config/</code> is writable.';
	mysqli_close($test_conn);
	return;
}

header('Location: admin.php?installed=1');
exit;
