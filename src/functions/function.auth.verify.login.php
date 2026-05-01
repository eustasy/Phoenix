<?php

////	auth_verify_login
// Verify login credentials against the admin password.
// Returns true if credentials are valid, false otherwise.

function auth_verify_login($settings) {
	return isset($_POST['password']) &&
	       password_verify($_POST['password'], $settings['admin_password']);
}
