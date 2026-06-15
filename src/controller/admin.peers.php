<?php

declare(strict_types=1);

////	admin_peers_controller
// Renders the admin global Peers page (the swarm-wide listing). This is the UI
// only — it does not query the tracker (the page shows preview/sample rows).
// Dispatched by admin_panel_controller() for page=peers when no info_hash is
// present; with an info_hash the router routes to the live per-torrent
// drill-down instead. Only needs $settings (and the CSRF token for the layout's
// logout form).

/** @param PhoenixSettings $settings */
function admin_peers_controller(array $settings): string
{
    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.peers.php';

    return view_admin_peers_html($settings, $csrf_token);
}
