<?php

declare(strict_types=1);

////	auth_verify_login
// Verify login credentials against the admin password, plus an optional TOTP
// second factor when admin_totp_secret is configured.
// Returns true if credentials are valid, false otherwise.

/** @param PhoenixSettings $settings */
function auth_verify_login(array $settings): bool
{
    ////	Password (always required, verified first and independently)
    // A correct second factor can never compensate for a wrong password: if the
    // password check fails we return false before ever looking at the code.
    if (
        ! isset($_POST['password']) ||
        ! password_verify($_POST['password'], $settings['admin_password'])
    ) {
        return false;
    }

    ////	TOTP second factor (only when a secret is enrolled)
    if (! empty($settings['admin_totp_secret'])) {
        // Fail closed: a secret is enrolled but the verification library is not
        // available, so we cannot check the second factor. Deny rather than
        // silently downgrade to password-only.
        if (! class_exists(\eustasy\Authenticatron::class)) {
            return false;
        }

        $code = isset($_POST['code']) && is_string($_POST['code']) ? $_POST['code'] : '';

        return \eustasy\Authenticatron::checkCode($code, $settings['admin_totp_secret']);
    }

    return true;
}
