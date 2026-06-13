<?php

declare(strict_types=1);

////	admin_panel_controller
// Light page router for the authenticated admin panel. Selects the page
// from $_GET['page'] (defaulting to, and falling back to, the dashboard
// for any unrecognised value), then dispatches to that page's handler.
// Returns the rendered HTML string. Caller is responsible for echoing
// and exiting.

/** @param PhoenixSettings $settings */
function admin_panel_controller(mysqli $connection, array $settings, int $time): string
{
    ////	Page selection
    // Normalise the requested page up front. Unknown pages fall through to
    // the dashboard (lenient — never error on a bogus ?page=).
    $page = isset($_GET['page']) ? (string) $_GET['page'] : 'dashboard';

    switch ($page) {
        case 'torrents':
            require_once __DIR__.'/admin.torrents.php';

            return admin_torrents_controller($connection, $settings);

            // Extension point: issues #57–#58 add 'backups'/'settings' cases here,
            // each requiring + delegating to its own page controller (one function
            // per file, as with admin.dashboard.php / admin.torrents.php).
        case 'dashboard':
        default:
            require_once __DIR__.'/admin.dashboard.php';

            return admin_dashboard_page($connection, $settings, $time);
    }
}
