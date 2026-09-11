<?php

declare(strict_types=1);

////	admin_torrents_controller
// Renders the admin Torrents management page: lists torrents (any owner, listed
// or not) with swarm stats and offers a per-row List/Unlist toggle and Delete.
// Verifies the CSRF token on the state-changing POSTs (torrent_listed /
// torrent_delete) before dispatching to the matching action, then renders the
// table via the shared layout. Dispatched by admin_panel_controller() for
// page=torrents.
//
// The listing is paged, searched and sorted in SQL via ?q / ?listed / ?sort /
// ?dir / ?offset — the same shape the Peers page uses, and for the same reason:
// a row per torrent is unbounded work, and a browser-side filter would only
// ever search the page it was given.

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
    if (! empty($_POST['process']) && is_string($_POST['process'])) {
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

    // Only query once the tables exist (the models bail via tracker_error on a
    // missing table).
    // All five are untrusted: the model binds $search and whitelists $sort/$dir,
    // and $listed is narrowed to the two valid flags here. Seeders descending is
    // the default because the first question of a tracker listing is what is
    // actually being served.
    $limit = max(1, intval($settings['admin_torrents_limit']));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    $search = is_string($_GET['q'] ?? null) ? trim((string) $_GET['q']) : '';
    $listed = isset($_GET['listed']) && ($_GET['listed'] === '0' || $_GET['listed'] === '1')
        ? (int) $_GET['listed']
        : -1;
    $sort = is_string($_GET['sort'] ?? null) ? (string) $_GET['sort'] : 'seeders';
    $dir = ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc';

    require_once __DIR__.'/../model/db.tables.installed.php';
    $torrents = [];
    $swarms = [];
    $total = 0;
    if (db_tables_installed($connection, $settings)) {
        require_once __DIR__.'/../model/torrents.select.all.php';
        $torrents = torrents_select_all($connection, $settings, null, $limit, $offset, $search, $listed, $sort, $dir);

        // The pager counts what the filter matched, not the whole table, or a
        // filtered list would page against an unfiltered total and trail off
        // into empty pages.
        require_once __DIR__.'/../model/torrents.count.php';
        $total = torrents_count($connection, $settings, $search, $listed);

        // Swarms with peers but no torrents row (e.g. open-tracker hashes never
        // registered) — counted and shown so they aren't invisible to the admin.
        // Only on the unfiltered first page: it is a footnote about the tracker,
        // not a second listing to page through.
        if ($search === '' && $listed === -1 && $offset === 0) {
            require_once __DIR__.'/../model/peers.select.unregistered.php';
            $swarms = peers_select_unregistered($connection, $settings);
        }
    } elseif ($message === false) {
        $message = 'Tables are not installed.';
    }

    $csrf_token = $csrf_enabled ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.torrents.php';

    return view_admin_torrents_html($settings, $torrents, $message, $csrf_token, $swarms, $total, $offset, $limit, $search, $listed, $sort, $dir);
}
