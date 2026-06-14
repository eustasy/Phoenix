<?php

declare(strict_types=1);

////	admin_support_controller
// Renders the admin Server Support page: PHP/MySQL/extension diagnostics, the
// installed-tables check, and the current database size. Read-only — there are
// no forms, so no CSRF or action dispatch (the only token built is for the
// layout's logout form). Dispatched by admin_panel_controller() for
// page=support.

/** @param PhoenixSettings $settings */
function admin_support_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../model/db.tables.installed.php';
    $tables_installed = db_tables_installed($connection, $settings);

    $database_size = false;
    if ($tables_installed) {
        require_once __DIR__.'/../model/db.size.php';
        $database_size = db_size($connection, $settings);
    }

    // Token only for the layout's logout form; this page has no forms of its own.
    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.support.php';

    return view_admin_support_html($settings, $tables_installed, $database_size, $csrf_token);
}
