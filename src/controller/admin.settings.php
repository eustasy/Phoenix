<?php

declare(strict_types=1);

////	admin_settings_controller
// Renders the admin Settings page: a read-only view of the effective settings
// (with secrets masked) plus, when config/ is writable, forms to change the
// admin password, enable/disable two-factor auth, and toggle the open_tracker /
// public_index / full_scrape / db_reset flags. Verifies the CSRF token on the
// state-changing POSTs (process=password / settings / totp_enable / totp_disable)
// and refuses edits when the config directory is not writable. Dispatched by
// admin_panel_controller() for page=settings. $config_path is injectable for
// tests; it defaults to the real phoenix.custom.php.

/** @param PhoenixSettings $settings */
function admin_settings_controller(array $settings, ?string $config_path = null): string
{
    $config_path ??= __DIR__.'/../../config/phoenix.custom.php';

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    require_once __DIR__.'/../functions/auth.csrf.verify.php';

    $csrf_enabled = ! empty($settings['admin_password']);

    // config/ is often deliberately not web-writable (it holds DB credentials
    // and sits outside the document root). Editing is gated on this; the view
    // explains the read-only state when it is false.
    $writable = is_writable(dirname($config_path));

    $process = '';
    if (! empty($_POST['process'])) {
        $process = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
    }

    $message = false;

    if ($process !== '' && $csrf_enabled && ! auth_csrf_verify()) {
        $message = 'Security check failed. Please reload the page and try again.';
        $process = '';
    }

    if ($process !== '' && ! $writable) {
        $message = 'The config directory is not writable, so changes cannot be saved.';
        $process = '';
    }

    if ($process === 'password') {
        require_once __DIR__.'/admin.password.php';
        $message = admin_password_action($config_path);
    } elseif ($process === 'settings') {
        require_once __DIR__.'/admin.settings.save.php';
        $message = admin_settings_save_action($config_path);
        // The in-memory $settings is otherwise stale until the next request;
        // reflect the submitted flags so the checkboxes match what was saved.
        foreach (['open_tracker', 'public_index', 'full_scrape', 'stats_enabled', 'stats_geo', 'db_reset'] as $flag) {
            $settings[$flag] = isset($_POST[$flag]);
        }
    } elseif ($process === 'totp_enable') {
        require_once __DIR__.'/admin.totp.enable.php';
        [$message, $new_secret] = admin_totp_enable_action($config_path);
        // Reflect the change so the view shows the right state this request.
        if ($new_secret !== null) {
            $settings['admin_totp_secret'] = $new_secret;
        }
    } elseif ($process === 'totp_disable') {
        require_once __DIR__.'/admin.totp.disable.php';
        [$message, $new_secret] = admin_totp_disable_action($config_path, $settings['admin_totp_secret']);
        if ($new_secret !== null) {
            $settings['admin_totp_secret'] = $new_secret;
        }
    }

    // Two-factor enrolment presentation. When the verification library is
    // present, the config is writable, and no secret is enrolled yet, prepare a
    // candidate secret + QR for the enable form. A valid round-tripped secret
    // from a failed attempt is reused so the QR doesn't change under the admin.
    require_once __DIR__.'/../functions/install.valid.totp.secret.php';
    $totp_available = class_exists(\eustasy\Authenticatron::class);
    $totp_secret = null;
    $totp_qr = null;
    $totp_url = null;
    if ($totp_available && $writable && empty($settings['admin_totp_secret'])) {
        $posted = $_POST['totp_secret'] ?? null;
        $totp_secret = install_valid_totp_secret($posted) ? $posted : \eustasy\Authenticatron::makeSecret();
        $account = ! empty($settings['db_name']) ? (string) $settings['db_name'] : 'admin';
        $totp_url = \eustasy\Authenticatron::getUrl($account, $totp_secret, 'Phoenix');
        // generateQrCode() needs ext-gd; without it the library fatals (its
        // catch misses the Error), so guard and fall back to manual entry.
        $totp_qr = extension_loaded('gd') ? \eustasy\Authenticatron::generateQrCode($totp_url) : null;
    }

    $csrf_token = $csrf_enabled ? auth_csrf_token() : '';

    // Geo enrichment is available only with both the geoip2 library and a
    // readable GeoLite2 database; the view greys out the stats_geo toggle when
    // it isn't, so the operator can't enable a no-op.
    require_once __DIR__.'/../functions/stats.geo.database.php';
    $geo_available = class_exists(\GeoIp2\Database\Reader::class) && stats_geo_database($settings) !== '';

    require_once __DIR__.'/../views/html.admin.settings.php';

    return view_admin_settings_html($settings, $writable, $message, $csrf_token, $totp_available, $totp_secret, $totp_qr, $totp_url, $geo_available);
}
