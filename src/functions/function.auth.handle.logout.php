<?php

////	auth_handle_logout
// Handle logout request by destroying the session and redirecting.
// Calls exit() after redirect.

function auth_handle_logout() {
	if ( isset($_GET['logout']) ) {
		session_destroy();
		header('Location: '.strtok($_SERVER['REQUEST_URI'], '?'));
		exit;
	}
}
