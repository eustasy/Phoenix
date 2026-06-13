<?php

declare(strict_types=1);

////	admin_torrents_controller
// Renders the admin Torrents management page: lists every torrent (any owner,
// listed or not) with swarm stats and offers a per-row List/Unlist toggle and
// Delete. Verifies the CSRF token on the state-changing POSTs (torrent_listed /
// torrent_delete) before dispatching to the matching action, then renders the
// table via the shared layout. Dispatched by admin_panel_controller() for
// page=torrents.

/** @param PhoenixSettings $settings */
function admin_torrents_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../functions/auth.csrf.token.php';
    require_once __DIR__.'/../functions/auth.csrf.verify.php';

    // CSRF only matters when a password (hence a session) is in play; with
    // admin_password empty the panel is unauthenticated, so there is no
    // boundary for a forged request to cross. (Mirrors admin_dashboard_page.)
    $csrf_enabled = ! empty($settings['admin_password']);

    $process = '';
    if (! empty($_POST['process'])) {
        $process = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
    }

    $message = false;

    // Reject any state-changing POST whose CSRF token is missing or wrong;
    // surface a message and skip dispatch so the page still renders.
    if ($process !== '' && $csrf_enabled && ! auth_csrf_verify()) {
        $message = 'Security check failed. Please reload the page and try again.';
        $process = '';
    }

    if ($process === 'torrent_listed') {
        require_once __DIR__.'/admin.torrent.listed.php';
        $message = admin_torrent_listed_action($connection, $settings);
    } elseif ($process === 'torrent_delete') {
        require_once __DIR__.'/admin.torrent.delete.php';
        $message = admin_torrent_delete_action($connection, $settings);
    }

    // Only query the torrents table once it exists (torrents_select_all bails
    // via tracker_error on a missing table).
    require_once __DIR__.'/../model/db.tables.installed.php';
    $torrents = [];
    if (db_tables_installed($connection, $settings)) {
        require_once __DIR__.'/../model/torrents.select.all.php';
        $torrents = torrents_select_all($connection, $settings);
    } elseif ($message === false) {
        $message = 'Tables are not installed.';
    }

    $csrf_token = $csrf_enabled ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.torrents.php';

    return view_admin_torrents_html($settings, $torrents, $message, $csrf_token);
}
