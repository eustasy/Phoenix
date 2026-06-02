<?php

declare(strict_types=1);

////	auth_is_authenticated
// Check if the current session is authenticated.
// Returns true if authenticated, false otherwise.

function auth_is_authenticated(): bool
{
    return ! empty($_SESSION['phoenix_authed']);
}
