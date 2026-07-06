<?php

declare(strict_types=1);

////	admin_install_controller
//  Handles first-run installer mode when no config file exists.
//  Returns HTML output.

require_once __DIR__.'/../views/html.install.php';

function admin_install_controller(string $config_path): string
{
    error_reporting(0);

    require_once __DIR__.'/../functions/install.sanitize.post.php';
    $values = install_sanitize_post($_POST);

    $settings_writable = is_writable(dirname($config_path));
    $install_error = null;

    // Setup-token gate: the token file lives in config/ (out
    // of the docroot), so completing setup requires filesystem access — proving
    // the requester is the operator, not a stranger who reached admin.php first.
    // Created on first load; verified below before any DB probe or config write.
    require_once __DIR__.'/../functions/install.setup.token.php';
    $token_path = dirname($config_path).'/.phoenix-setup-token';
    $setup_token = $settings_writable ? install_setup_token($token_path) : '';

    ////	Optional two-factor enrolment
    // Only offered when the verification library is present. We pick a candidate
    // secret to display: a valid round-tripped one from a previous (failed)
    // submission so re-scanning isn't forced, otherwise a fresh one. The secret,
    // its otpauth URL, and a base64 PNG QR (when GD is available) are handed to
    // the view; the view shows the section only when $totp_secret is non-null.
    require_once __DIR__.'/../functions/install.valid.totp.secret.php';
    $totp_secret = null;
    $totp_qr = null;
    $totp_url = null;
    if (class_exists(\eustasy\Authenticatron::class)) {
        $posted_secret = $_POST['totp_secret'] ?? null;
        $totp_secret = install_valid_totp_secret($posted_secret)
            ? $posted_secret
            : \eustasy\Authenticatron::makeSecret();

        $account = $values['db_name'] !== '' ? $values['db_name'] : 'admin';
        $totp_url = \eustasy\Authenticatron::getUrl($account, $totp_secret, 'Phoenix');
        // generateQrCode() returns a complete "data:image/png;base64,..." data
        // URI ready for an <img src>. It needs ext-gd; without it the library
        // fatals (its catch misses the Error), so guard and fall back to the
        // manual-entry secret + otpauth link the view already shows.
        $totp_qr = extension_loaded('gd') ? \eustasy\Authenticatron::generateQrCode($totp_url) : null;
    }

    ////	Geo availability
    // stats_geo is only selectable with both the geoip2 library and a
    // discoverable GeoLite2 database; the view greys the checkbox out otherwise.
    // No $settings exists yet, so probe discovery with an empty configured path.
    require_once __DIR__.'/../functions/stats.geo.database.php';
    $geo_available = class_exists(\GeoIp2\Database\Reader::class) && stats_geo_database(['stats_geo_database' => '']) !== '';

    ////	Prepare form values (repopulate after failed attempt)
    $form = [
        'db_host' => $values['db_host'],
        'db_user' => $values['db_user'],
        'db_name' => $values['db_name'],
        'db_prefix' => $values['db_prefix'] !== '' ? $values['db_prefix'] : 'phoenix_',
        'db_persist' => ! empty($_POST) ? $values['db_persist'] : true,
        'open_tracker' => $values['open_tracker'],
        'public_index' => $values['public_index'],
        'stats_enabled' => $values['stats_enabled'],
        'stats_geo' => $values['stats_geo'],
        'geo_available' => $geo_available,
    ];


    ////	Process installation
    // 'process' is part of the request, not the sanitised config payload, so it
    // is read from $_POST directly. Without this, install_sanitize_post() never
    // sets it and the controller could never reach the install branch.
    if (($_POST['process'] ?? '') !== 'install') {
        return view_install_html($settings_writable, $install_error, $form, $totp_secret, $totp_qr, $totp_url);
    }

    // Require an admin password at setup so a fresh install is never left with an
    // unauthenticated panel. Operators who deliberately want no auth can still
    // hand-edit phoenix.custom.php — see the admin_password note there.
    if ($values['admin_password'] === '') {
        $install_error = 'Set an admin password to protect the control panel.';

        return view_install_html($settings_writable, $install_error, $form, $totp_secret, $totp_qr, $totp_url);
    }

    // Enforce the shared password policy on the raw plaintext (install_sanitize_post
    // has already hashed the accepted value into $values['admin_password']).
    require_once __DIR__.'/../functions/auth.password.valid.php';
    $raw_password = isset($_POST['admin_password']) && is_string($_POST['admin_password']) ? $_POST['admin_password'] : '';
    $password_error = auth_password_valid($raw_password);
    if ($password_error !== null) {
        $install_error = $password_error;

        return view_install_html($settings_writable, $install_error, $form, $totp_secret, $totp_qr, $totp_url);
    }

    if (! $settings_writable) {
        $install_error = 'The <code>config/</code> directory is not writable. Please make it writable and try again.';

        return view_install_html($settings_writable, $install_error, $form, $totp_secret, $totp_qr, $totp_url);
    }

    // Verify the setup token before any DB probe or config write. hash_equals is
    // timing-safe; the token was written to config/.phoenix-setup-token on first
    // load, where only someone with server access can read it.
    $submitted_token = isset($_POST['setup_token']) && is_string($_POST['setup_token']) ? trim($_POST['setup_token']) : '';
    if (! hash_equals($setup_token, $submitted_token)) {
        $install_error = 'The setup token is incorrect. Open config/.phoenix-setup-token on the server and paste its contents here.';

        return view_install_html($settings_writable, $install_error, $form, $totp_secret, $totp_qr, $totp_url);
    }

    ////	Validate the optional second factor before doing anything else
    // The admin proves their authenticator works by entering a code; we only
    // enrol the secret once it verifies, so they can't lock themselves out.
    // A blank code means "skip 2FA" and leaves admin_totp_secret unset (empty).
    $totp_code = isset($_POST['totp_code']) && is_string($_POST['totp_code']) ? trim($_POST['totp_code']) : '';
    if ($totp_code !== '') {
        if (
            ! install_valid_totp_secret($totp_secret) ||
            ! class_exists(\eustasy\Authenticatron::class) ||
            ! \eustasy\Authenticatron::checkCode($totp_code, $totp_secret)
        ) {
            $install_error = 'The two-factor code was incorrect. Re-scan and try again, or leave it blank to skip.';

            return view_install_html($settings_writable, $install_error, $form, $totp_secret, $totp_qr, $totp_url);
        }

        // Verified: this secret will be written to config.
        $values['admin_totp_secret'] = $totp_secret;
    }

    ////	Test DB connection before writing config
    $test_host = $values['db_persist'] ? 'p:' : '';
    $test_host .= $values['db_host'];
    try {
        $test_conn = @mysqli_connect($test_host, $values['db_user'], $values['db_pass'], $values['db_name']);
    } catch (mysqli_sql_exception $e) {
        $test_conn = false;
    }

    if (! $test_conn) {
        $install_error = 'Could not connect to the database: '.mysqli_connect_error();

        return view_install_html($settings_writable, $install_error, $form, $totp_secret, $totp_qr, $totp_url);
    }

    ////	Create tables
    require_once __DIR__.'/../model/db.create.php';
    if (! db_create($test_conn, $values)) {
        $install_error = 'Connected, but could not create the tables.';

        return view_install_html($settings_writable, $install_error, $form, $totp_secret, $totp_qr, $totp_url);
    }

    ////	Write config file
    require_once __DIR__.'/../functions/install.build.config.php';
    if (file_put_contents($config_path, install_build_config($values)) === false) {
        $install_error = 'Connected and created tables, but could not write the configuration file. Check that <code>config/</code> is writable.';

        return view_install_html($settings_writable, $install_error, $form, $totp_secret, $totp_qr, $totp_url);
    }

    // Setup complete — the token has served its purpose; remove it.
    @unlink($token_path);

    mysqli_close($test_conn);
    header('Location: admin.php?installed=1');
    exit;
}
