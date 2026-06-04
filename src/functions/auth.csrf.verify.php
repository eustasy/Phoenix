<?php

declare(strict_types=1);

////	auth_csrf_verify
// Constant-time check that the submitted `csrf` field matches the session
// token minted by auth_csrf_token(). Returns false when either side is absent
// or non-string, so a missing/forged token can never validate. The is_string
// guards keep hash_equals() from being handed a null or array.

function auth_csrf_verify(): bool
{
    $submitted = $_POST['csrf'] ?? null;

    return ! empty($_SESSION['phoenix_csrf'])
        && is_string($_SESSION['phoenix_csrf'])
        && is_string($submitted)
        && hash_equals($_SESSION['phoenix_csrf'], $submitted);
}
