<?php

declare(strict_types=1);

////	admin_apikeys_controller
// Renders the admin API Keys page: create a key (a freshly generated key shown
// ONCE, its SHA-256 hash stored), list existing keys by user, and revoke one.
// Verifies the CSRF token on the state-changing POSTs (process=apikey_create /
// apikey_revoke) and refuses edits when config/ is not writable. Dispatched by
// admin_panel_controller() for page=apikeys. $config_path is injectable for
// tests; it defaults to the real phoenix.custom.php.

/** @param PhoenixSettings $settings */
function admin_apikeys_controller(array $settings, ?string $config_path = null): string
{
    $config_path ??= __DIR__.'/../../config/phoenix.custom.php';

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    require_once __DIR__.'/../functions/auth.csrf.verify.php';

    $csrf_enabled = ! empty($settings['admin_password']);
    $writable = is_writable(dirname($config_path));

    $process = '';
    if (! empty($_POST['process'])) {
        $process = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
    }

    $message = false;
    $new_key = null;

    if ($process !== '' && $csrf_enabled && ! auth_csrf_verify()) {
        $message = 'Security check failed. Please reload the page and try again.';
        $process = '';
    }
    if ($process !== '' && ! $writable) {
        $message = 'The config directory is not writable, so changes cannot be saved.';
        $process = '';
    }

    if ($process === 'apikey_create') {
        require_once __DIR__.'/admin.apikey.create.php';
        [$message, $new_key, $updated] = admin_apikey_create_action($settings, $config_path);
        // Reflect the change so the list matches what was saved this request.
        if ($updated !== null) {
            $settings['api_keys'] = $updated;
        }
    } elseif ($process === 'apikey_revoke') {
        require_once __DIR__.'/admin.apikey.revoke.php';
        [$message, $updated] = admin_apikey_revoke_action($settings, $config_path);
        if ($updated !== null) {
            $settings['api_keys'] = $updated;
        }
    }

    $csrf_token = $csrf_enabled ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.apikeys.php';

    return view_admin_apikeys_html($settings, $writable, $message, $csrf_token, $new_key);
}
