<?php

declare(strict_types=1);

////	admin_login_controller
//  Handles authentication for admin panel.
//  Returns HTML output if login required, or null if authenticated.

function admin_login_controller($settings) {
	if (empty($settings['admin_password'])) {
		// No password configured, skip auth
		return null;
	}

	// Harden the session cookie before session_start() — params apply to the
	// cookie that is about to be sent. Secure is conditional on the request
	// arriving over HTTPS so local-dev plain-HTTP setups still work.
	session_set_cookie_params([
		'httponly' => true,
		'samesite' => 'Lax',
		'secure'   => !empty($_SERVER['HTTPS']),
	]);
	session_start();

	////	Handle logout

	require_once $settings['functions'].'function.auth.handle.logout.php';
	auth_handle_logout();

	////	Check authentication

	require_once $settings['functions'].'function.auth.is.authenticated.php';
	if (!auth_is_authenticated()) {
		$login_attempted = isset($_POST['process']) && $_POST['process'] === 'login';

		if ($login_attempted) {
			require_once $settings['functions'].'function.auth.verify.login.php';
			if (auth_verify_login($settings)) {
				// Defeat session-fixation: any pre-login session id is now
				// retired so an attacker who planted one cannot resume the
				// authenticated session.
				session_regenerate_id(true);
				require_once $settings['functions'].'function.auth.set.authenticated.php';
				auth_set_authenticated();
				header('Location: '.$_SERVER['REQUEST_URI']);
				exit;
			}
		}

		require_once $settings['views'].'html.login.php';
		return view_login_html($login_attempted);
	}

	// Authenticated, allow proceeding
	return null;
}
