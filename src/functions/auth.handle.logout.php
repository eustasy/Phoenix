<?php

declare(strict_types=1);

////	auth_handle_logout
// Handle logout request by destroying the session and redirecting.
// Calls exit() after redirect.
//
// Logout is POST-only so a third-party page cannot CSRF an admin out via a
// simple <img src="/admin.php?logout=1">.

function auth_handle_logout(): void
{
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' &&
        isset($_POST['logout'])
    ) {
        session_destroy();
        header('Location: '.strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
}
