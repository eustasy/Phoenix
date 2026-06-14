<?php

declare(strict_types=1);

////	admin_add_controller
// Renders the admin Add Torrent page: the add-a-torrent form (manual fields or
// a .torrent upload). Verifies the CSRF token on the add POST
// (process=torrent_add) before dispatching to admin_torrent_add_action, then
// renders the form via the shared layout. Dispatched by
// admin_panel_controller() for page=add.

/** @param PhoenixSettings $settings */
function admin_add_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../functions/auth.csrf.token.php';
    require_once __DIR__.'/../functions/auth.csrf.verify.php';

    // CSRF only matters when a password (hence a session) is in play; mirrors
    // admin_dashboard_page / admin_backups_controller.
    $csrf_enabled = ! empty($settings['admin_password']);

    $process = '';
    if (! empty($_POST['process'])) {
        $process = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
    }

    require_once __DIR__.'/../model/db.tables.installed.php';
    $tables_installed = db_tables_installed($connection, $settings);

    $message = false;

    if ($process !== '' && $csrf_enabled && ! auth_csrf_verify()) {
        $message = 'Security check failed. Please reload the page and try again.';
        $process = '';
    }

    if ($process === 'torrent_add') {
        require_once __DIR__.'/admin.torrent.add.php';
        $message = admin_torrent_add_action($connection, $settings);
    }

    $csrf_token = $csrf_enabled ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.add.php';

    return view_admin_add_html($settings, $tables_installed, $message, $csrf_token);
}
