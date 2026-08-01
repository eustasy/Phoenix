<?php

declare(strict_types=1);

////	api_admin_session_active
// Whether the request carries a logged-in admin.php session (the same
// $_SESSION['phoenix_authed'] flag admin_login_controller() sets after a
// successful password login). Lets a browser admin drive the API mutation
// endpoints without an API key — callers MUST still require a CSRF token on
// this path, since the session cookie is sent automatically by the browser.
//
// A session is started only when the request actually presents the session
// cookie, so cookieless (programmatic) callers never spin up a session here.
// The start goes through auth_session_start(), shared with
// admin_login_controller(), so we read the same hardened session rather than
// minting a divergent one.

function api_admin_session_active(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (! isset($_COOKIE[session_name()])) {
            return false;
        }

        require_once __DIR__.'/auth.session.start.php';
        auth_session_start();
    }

    require_once __DIR__.'/auth.is.authenticated.php';

    return auth_is_authenticated();
}
