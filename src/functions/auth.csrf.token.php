<?php

declare(strict_types=1);

////	auth_csrf_token
// Return the per-session CSRF token, minting one on first use. The token is
// embedded as a hidden field in every state-changing admin form and checked
// by auth_csrf_verify() on submission. Relies on the session already being
// started by admin_login_controller() (only done when admin_password is set).

function auth_csrf_token(): string
{
    if (empty($_SESSION['phoenix_csrf']) || ! is_string($_SESSION['phoenix_csrf'])) {
        $_SESSION['phoenix_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['phoenix_csrf'];
}
