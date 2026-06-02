<?php

declare(strict_types=1);

////	auth_verify_login
// Verify login credentials against the admin password.
// Returns true if credentials are valid, false otherwise.

/** @param PhoenixSettings $settings */
function auth_verify_login(array $settings): bool
{
    return isset($_POST['password']) &&
           password_verify($_POST['password'], $settings['admin_password']);
}
