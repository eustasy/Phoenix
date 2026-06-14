<?php

declare(strict_types=1);

////	admin_totp_disable_action
// Disables admin two-factor auth from the Settings page (process=totp_disable).
// Requires a current authenticator code so a hijacked but already-authenticated
// session cannot silently turn the second factor off. The caller has already
// verified the CSRF token and that the config is writable. Returns
// [message, $secret]: $secret is '' (disabled) on success, or null on failure
// (config unchanged).

/** @return array{0: string, 1: string|null} */
function admin_totp_disable_action(string $config_path, string $current_secret): array
{
    if (! class_exists(\eustasy\Authenticatron::class)) {
        return ['Two-factor support is not available on this server.', null];
    }

    $code = isset($_POST['totp_code']) && is_string($_POST['totp_code']) ? trim($_POST['totp_code']) : '';
    if ($code === '' || ! \eustasy\Authenticatron::checkCode($code, $current_secret)) {
        return ['The two-factor code was incorrect. Two-factor authentication is still enabled.', null];
    }

    require_once __DIR__.'/../functions/config.write.php';
    if (! config_write($config_path, ['admin_totp_secret' => ''])) {
        return ['Could not write the configuration file.', null];
    }

    return ['Two-factor authentication disabled.', ''];
}
