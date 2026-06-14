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

        case 'peers':
            require_once __DIR__.'/admin.torrent.peers.php';

            return admin_torrent_peers_controller($connection, $settings);

        case 'add':
            require_once __DIR__.'/admin.add.php';

            return admin_add_controller($connection, $settings);

        case 'support':
            require_once __DIR__.'/admin.support.php';

            return admin_support_controller($connection, $settings);

        case 'utilities':
            require_once __DIR__.'/admin.utilities.php';

            return admin_utilities_controller($connection, $settings, $time);

        case 'backups':
            require_once __DIR__.'/admin.backups.php';

            return admin_backups_controller($connection, $settings, $time);

        case 'settings':
            require_once __DIR__.'/admin.settings.php';

            return admin_settings_controller($settings);

        case 'dashboard':
        default:
            require_once __DIR__.'/admin.dashboard.php';

            return admin_dashboard_page($connection, $settings);
    }
}
