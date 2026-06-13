<?php

declare(strict_types=1);

////	admin_settings_controller
// Renders the admin Settings page: a read-only view of the effective settings
// (with secrets masked) plus, when config/ is writable, forms to change the
// admin password and toggle the open_tracker / public_index / full_scrape /
// db_reset flags. Verifies the CSRF token on the state-changing POSTs
// (process=password / process=settings) and refuses edits when the config
// directory is not writable. Dispatched by admin_panel_controller() for
// page=settings. $config_path is injectable for tests; it defaults to the real
// phoenix.custom.php.

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
        foreach (['open_tracker', 'public_index', 'full_scrape', 'db_reset'] as $flag) {
            $settings[$flag] = isset($_POST[$flag]);
        }
    }

    $csrf_token = $csrf_enabled ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.settings.php';

    return view_admin_settings_html($settings, $writable, $message, $csrf_token);
}
