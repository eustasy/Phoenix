<?php

////	auth_set_authenticated
// Mark the current session as authenticated.

function auth_set_authenticated() {
	$_SESSION['phoenix_authed'] = true;
}
