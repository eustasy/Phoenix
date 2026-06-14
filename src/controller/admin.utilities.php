<?php

declare(strict_types=1);

////	admin_utilities_controller
// Renders the admin Utilities page: the database setup/reset, clean, optimize,
// and schema-migrate maintenance actions. Parses any submitted action, verifies
// the CSRF token on state-changing POSTs, dispatches to the matching
// admin_*_action helper, then renders the forms via the shared layout.
// Dispatched by admin_panel_controller() for page=utilities.

/** @param PhoenixSettings $settings */
function admin_utilities_controller(mysqli $connection, array $settings, int $time): string
{
    require_once __DIR__.'/../functions/auth.csrf.token.php';
    require_once __DIR__.'/../functions/auth.csrf.verify.php';

    // CSRF is only meaningful when a password (and therefore a session) is in
    // play; mirrors admin_dashboard_page / admin_backups_controller.
    $csrf_enabled = ! empty($settings['admin_password']);

    $process = '';
    if (! empty($_POST['process'])) {
        // $process is only ever compared against literal action names below,
        // but htmlentities-ing it keeps any reflection of the value into HTML
        // safe should a future render path emit it.
        $process = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
    }

    require_once __DIR__.'/../model/db.tables.installed.php';
    $tables_installed = db_tables_installed($connection, $settings);

    $message = false;

    // Reject any state-changing POST whose CSRF token is missing or wrong, so a
    // forged form cannot drive setup/clean/optimize against an authenticated
    // admin. Surface a message and skip dispatch (rather than tracker_error())
    // so the page still renders and the admin can simply retry.
    if ($process !== '' && $csrf_enabled && ! auth_csrf_verify()) {
        $message = 'Security check failed. Please reload the page and try again.';
        $process = '';
    }

    if ($process === 'setup') {
        require_once __DIR__.'/admin.setup.php';
        $result = admin_setup_action($connection, $settings, $time, $tables_installed);
        if ($result !== false) {
            $message = $result;
            $tables_installed = true;
        }
    } elseif ($process === 'clean') {
        require_once __DIR__.'/admin.clean.php';
        $message = admin_clean_action($connection, $settings, $time);
    } elseif ($process === 'optimize') {
        require_once __DIR__.'/admin.optimize.php';
        $message = admin_optimize_action($connection, $settings, $time);
    } elseif ($process === 'migrate') {
        require_once __DIR__.'/admin.migrate.php';
        $message = admin_migrate_action($connection, $settings, $time);
    }

    $csrf_token = $csrf_enabled ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.utilities.php';

    return view_admin_utilities_html($settings, $tables_installed, $message, $csrf_token);
}
