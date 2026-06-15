<?php

declare(strict_types=1);

////	admin_upload_controller
// Renders the admin Bulk Upload page: a drop zone / picker that sends each
// selected .torrent straight to the add API (POST /api/torrent/add.php) from
// the browser, with no per-file form. The uploads ride the admin session, so
// they need the CSRF token the API's session path requires — which only exists
// when an admin password is set; the view explains this when it doesn't.
// Dispatched by admin_panel_controller() for page=upload. Marks the Add nav
// active (this is a sibling of the single-add form).

/** @param PhoenixSettings $settings */
function admin_upload_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../model/db.tables.installed.php';
    $tables_installed = db_tables_installed($connection, $settings);

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.upload.php';

    return view_admin_upload_html($settings, $tables_installed, $csrf_token);
}
