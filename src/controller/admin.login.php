<?php

////	admin_login_controller
//  Handles authentication for admin panel.
//  Returns HTML output if login required, or null if authenticated.

function admin_login_controller($settings) {
	if (empty($settings['admin_password'])) {
		// No password configured, skip auth
		return null;
	}

	session_start();

	////	Handle logout

	require_once $settings['functions'].'function.auth.handle.logout.php';
	auth_handle_logout();

	////	Check authentication

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
		return view_login_html($login_error);
	}

	// Authenticated, allow proceeding
	return null;
}
