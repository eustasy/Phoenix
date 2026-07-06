<?php

declare(strict_types=1);

////	admin_password_action
// Handles the Settings page admin-password change (process=password). Hashes
// the new password with password_hash(PASSWORD_DEFAULT) — exactly as the
// installer does — and persists only that key via config_write (every other
// custom setting is preserved). Returns a message for the panel. The caller
// has already verified the CSRF token and that the config is writable.

function admin_password_action(string $config_path): string
{
    $new = $_POST['new_password'] ?? '';
    if (! is_string($new) || $new === '') {
        return 'The new password cannot be empty.';
    }

    require_once __DIR__.'/../functions/auth.password.valid.php';
    $invalid = auth_password_valid($new);
    if ($invalid !== null) {
        return $invalid;
    }

    require_once __DIR__.'/../functions/config.write.php';
    $hash = password_hash($new, PASSWORD_DEFAULT);

    if (! config_write($config_path, ['admin_password' => $hash])) {
        return 'Could not write the configuration file.';
    }

    return 'Admin password changed.';
}
