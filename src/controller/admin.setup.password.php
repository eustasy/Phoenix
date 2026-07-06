<?php

declare(strict_types=1);

////	admin_setup_password_controller
// First-run "set admin password" gate (finding #8). Reached from
// admin_login_controller when admin_password is empty and the operator has NOT
// set admin_auth_optional. Forces a password (auth_password_valid policy) with
// an OPTIONAL TOTP enrolment mirroring the installer, persists them via
// config_write, then redirects to the panel (which now requires a login).
//
// Like the installer, this step is itself unauthenticated — it only exists in
// the window where the panel would otherwise be fully open — so the operator
// should complete it immediately. Returns the rendered HTML, or never returns
// on the success redirect (header + exit).

require_once __DIR__.'/../views/html.setup.password.php';

/** @param PhoenixSettings $settings */
function admin_setup_password_controller(array $settings, string $config_path): string
{
    require_once __DIR__.'/../functions/install.valid.totp.secret.php';

    $writable = is_writable(dirname($config_path));
    $version = $settings['phoenix_version'];

    ////	Optional TOTP candidate
    // Round-trip a valid submitted secret (so a failed attempt keeps the same QR)
    // otherwise mint a fresh one — only when the verification library is present.
    $totp_secret = null;
    $totp_qr = null;
    $totp_url = null;
    if (class_exists(\eustasy\Authenticatron::class)) {
        $posted = $_POST['totp_secret'] ?? null;
        $totp_secret = install_valid_totp_secret($posted) && is_string($posted)
            ? $posted
            : (string) \eustasy\Authenticatron::makeSecret();
        $totp_url = (string) \eustasy\Authenticatron::getUrl('admin', $totp_secret, 'Phoenix');
        $totp_qr = extension_loaded('gd') ? (string) \eustasy\Authenticatron::generateQrCode($totp_url) : null;
    }

    if (($_POST['process'] ?? '') !== 'setup_password') {
        return view_setup_password_html(null, $totp_secret, $totp_qr, $totp_url, $writable, $version);
    }

    if (! $writable) {
        return view_setup_password_html(
            'The config/ directory is not writable. Make it writable and try again.',
            $totp_secret,
            $totp_qr,
            $totp_url,
            $writable,
            $version,
        );
    }

    ////	Validate the password against the shared policy
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $confirm = isset($_POST['confirm']) && is_string($_POST['confirm']) ? $_POST['confirm'] : '';

    require_once __DIR__.'/../functions/auth.password.valid.php';
    $invalid = auth_password_valid($password);
    if ($invalid !== null) {
        return view_setup_password_html($invalid, $totp_secret, $totp_qr, $totp_url, $writable, $version);
    }
    if ($password !== $confirm) {
        return view_setup_password_html('The passwords did not match.', $totp_secret, $totp_qr, $totp_url, $writable, $version);
    }

    $values = ['admin_password' => password_hash($password, PASSWORD_DEFAULT)];

    ////	Optional second factor — a blank code skips it, same as the installer.
    // Verified before it can be written, so an operator can never enrol a secret
    // their authenticator cannot produce.
    $code = isset($_POST['totp_code']) && is_string($_POST['totp_code']) ? trim($_POST['totp_code']) : '';
    if ($code !== '') {
        if (
            ! is_string($totp_secret) ||
            ! install_valid_totp_secret($totp_secret) ||
            ! class_exists(\eustasy\Authenticatron::class) ||
            ! \eustasy\Authenticatron::checkCode($code, $totp_secret)
        ) {
            return view_setup_password_html(
                'The two-factor code was incorrect. Re-scan and try again, or leave it blank to skip.',
                $totp_secret,
                $totp_qr,
                $totp_url,
                $writable,
                $version,
            );
        }
        $values['admin_totp_secret'] = $totp_secret;
    }

    require_once __DIR__.'/../functions/config.write.php';
    if (! config_write($config_path, $values)) {
        return view_setup_password_html('Could not write the configuration file.', $totp_secret, $totp_qr, $totp_url, $writable, $version);
    }

    header('Location: admin.php');
    exit;
}
