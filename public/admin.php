<?php

declare(strict_types=1);

////	Admin Panel & Installer
// This page is not secure.
// It should not be deployed in a production environment.

////	Bootstrap tracker_error before any DB work so the installer-mode path
//      below can use it without going through phoenix.php's full DB connect.

require_once __DIR__.'/../src/functions/function.tracker.error.php';

$config_path   = __DIR__.'/../config/phoenix.custom.php';
$config_exists = is_readable($config_path);

////	Installer Mode (no config file exists)
if (!$config_exists) {
	require_once __DIR__.'/../src/controller/admin.install.php';
	echo admin_install_controller($settings, $config_path);
	exit;
}

////	Normal Admin Mode (full bootstrap)
require_once __DIR__.'/../src/phoenix.php';

////	Authentication
require_once __DIR__.'/../src/controller/admin.login.php';
$login_output = admin_login_controller($settings);
if ($login_output !== null) {
	echo $login_output;
	exit;
}

////	Render admin panel
require_once __DIR__.'/../src/controller/admin.panel.php';
echo admin_panel_controller($connection, $settings, $time);
