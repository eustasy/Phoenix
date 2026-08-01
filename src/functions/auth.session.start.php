<?php

declare(strict_types=1);

////	auth_session_start
// Start the admin session with the hardened cookie parameters, once. The params
// must be set before session_start() — they apply to the cookie that is about
// to be sent — and `secure` is conditional on the request arriving over HTTPS so
// a local-dev plain-HTTP setup still works.
//
// Shared by admin_login_controller() and api_admin_session_active() so both read
// the SAME cookie: divergent params would mint two sessions for one browser and
// silently break the API's session path. A no-op when a session is already
// active, so either caller may run first.

function auth_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => ! empty($_SERVER['HTTPS']),
    ]);
    session_start();
}
