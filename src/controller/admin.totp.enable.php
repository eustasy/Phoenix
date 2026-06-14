<?php

declare(strict_types=1);

////	admin_totp_enable_action
// Enables admin two-factor auth from the Settings page (process=totp_enable).
// The form round-trips the candidate secret shown in the QR; the secret is only
// persisted once the submitted code verifies against it, so an admin can never
// enrol a secret their authenticator can't produce (no self-lockout). The
// caller has already verified the CSRF token and that the config is writable.
// Returns [message, $secret]: $secret is the newly enrolled secret on success,
// or null on failure (config unchanged).

/** @return array{0: string, 1: string|null} */
function admin_totp_enable_action(string $config_path): array
{
    if (! class_exists(\eustasy\Authenticatron::class)) {
        return ['Two-factor support is not available on this server.', null];
    }

    require_once __DIR__.'/../functions/install.valid.totp.secret.php';
    $secret = $_POST['totp_secret'] ?? null;
    $code = isset($_POST['totp_code']) && is_string($_POST['totp_code']) ? trim($_POST['totp_code']) : '';

    if (! install_valid_totp_secret($secret)) {
        return ['Could not enable two-factor authentication: the secret was invalid. Reload and try again.', null];
    }

    if ($code === '' || ! \eustasy\Authenticatron::checkCode($code, $secret)) {
        return ['The two-factor code was incorrect. Re-scan the code and try again.', null];
    }

    require_once __DIR__.'/../functions/config.write.php';
    if (! config_write($config_path, ['admin_totp_secret' => $secret])) {
        return ['Could not write the configuration file.', null];
    }

    return ['Two-factor authentication enabled.', $secret];
}
