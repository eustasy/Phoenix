<?php

declare(strict_types=1);

////	auth_rehash_password
// Transparently upgrade the stored admin password hash after a SUCCESSFUL login
// when PASSWORD_DEFAULT has moved on since the hash was created — a new default
// algorithm (e.g. bcrypt → argon2 in a future PHP) or a higher default cost.
// Re-hashes the just-verified plaintext and persists only that key via
// config_write, exactly as admin_password_action / the installer do.
//
// Best-effort by design: only call it once the password has verified (it does
// NOT re-check the password), and a read-only config — common in hardened
// deployments — simply leaves the hash as-is, retried on the next login, never
// blocking sign-in.

/** @param PhoenixSettings $settings */
function auth_rehash_password(array $settings, string $password, string $config_path): void
{
    if ($password === '' || $settings['admin_password'] === '') {
        return;
    }
    if (! password_needs_rehash($settings['admin_password'], PASSWORD_DEFAULT)) {
        return;
    }

    require_once __DIR__.'/config.write.php';
    config_write($config_path, ['admin_password' => password_hash($password, PASSWORD_DEFAULT)]);
}
