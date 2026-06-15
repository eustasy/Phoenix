<?php

declare(strict_types=1);

////	admin_geography_controller
// Renders the admin Geography page. This is the UI only — it does not query the
// tracker (the page shows preview/sample figures). Dispatched by
// admin_panel_controller() for page=geography. Only needs $settings (and the
// CSRF token for the layout's logout form).

/** @param PhoenixSettings $settings */
function admin_geography_controller(array $settings): string
{
    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.geography.php';

    return view_admin_geography_html($settings, $csrf_token);
}
