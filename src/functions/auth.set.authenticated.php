<?php

declare(strict_types=1);

////	auth_set_authenticated
// Mark the current session as authenticated.

function auth_set_authenticated(): void {
	$_SESSION['phoenix_authed'] = true;
}
