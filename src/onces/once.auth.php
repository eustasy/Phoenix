<?php

if ( empty($settings['admin_password']) ) {
	return;
}

session_start();

require_once $settings['functions'].'function.auth.handle.logout.php';
auth_handle_logout();

require_once $settings['functions'].'function.auth.is.authenticated.php';
if ( auth_is_authenticated() ) {
	return;
}

$login_error = isset($_POST['process']) && $_POST['process'] === 'login';

if ( $login_error ) {
	require_once $settings['functions'].'function.auth.verify.login.php';
	if ( auth_verify_login($settings) ) {
		require_once $settings['functions'].'function.auth.set.authenticated.php';
		auth_set_authenticated();
		header('Location: '.$_SERVER['REQUEST_URI']);
		exit;
	}
}

require_once $settings['views'].'html.login.php';
echo view_login_html($login_error);
exit;
