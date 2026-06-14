<?php

declare(strict_types=1);

////	view_admin_html
// Render the admin panel's Dashboard page: the tracker-statistics overview
// (peer/torrent/download aggregates plus the last-run maintenance timestamps)
// and the post-install confirmation banner. The diagnostics, maintenance
// actions, and add-a-torrent form live on their own pages (Server Support,
// Utilities, Add Torrent). Wrapped in the shared admin layout; the layout owns
// the full HTML document and this view builds only the Dashboard body.
// Returns HTML string. Caller is responsible for echo and exit.
//
// Parameters:
//   $settings - settings array
//   $tables_installed - bool, whether all tables are installed (drives the
//                       empty-state message when there are no stats yet)
//   $show_installed - bool, whether to show the "Installation complete" banner
//   $csrf_token - string, per-session token for the layout's logout form (empty
//                 when no admin_password is set, since CSRF is not enforced then)
//   $stats - array<string,int>|false, merged tracker stats (seeders, leechers,
//            peers, torrents, downloads, traffic) plus 'registered' (total
//            torrents). False hides the stats block (e.g. tables not installed).
//   $tasks - array<string,int>, maintenance task name => last-run Unix
//            timestamp, for the "Last cleaned/optimized/…" lines.

/**
 * @param PhoenixSettings $settings
 * @param array<string, int>|false $stats
 * @param array<string, int> $tasks
 */
function view_admin_html(array $settings, bool $tables_installed, bool $show_installed = false, string $csrf_token = '', array|false $stats = false, array $tasks = []): string
{
    $body = '<h1>Dashboard</h1>';

    // Build installation complete banner
    if ($show_installed) {
        $body .= '<p class="box background-green-sea color-clouds">Installation complete.</p>';
    }

    if ($stats !== false) {
        // Tracker stats — already-computed aggregates; number_format() matches
        // the figure formatting used elsewhere in the panel.
        $body .= '<h2>Tracker Stats</h2>
	<p class="box background-clouds">Seeders '.number_format($stats['seeders']).' &middot; Leechers '.number_format($stats['leechers']).' &middot; Peers '.number_format($stats['peers']).'</p>
	<p class="box background-clouds">Registered torrents '.number_format($stats['registered'] ?? 0).' &middot; With active peers '.number_format($stats['torrents']).'</p>
	<p class="box background-clouds">Completed downloads '.number_format($stats['downloads']).' &middot; Traffic '.number_format($stats['traffic']).' bytes</p>';

        // Last-run timestamp for each maintenance task that has ever run.
        $task_labels = [
            'install' => 'Last installed',
            'migrate' => 'Last migrated',
            'clean' => 'Last cleaned',
            'optimize' => 'Last optimized',
        ];
        foreach ($task_labels as $task_name => $label) {
            if (isset($tasks[$task_name])) {
                $body .= '
	<p class="color-asbestos">'.$label.': '.date('Y-m-d H:i', $tasks[$task_name]).'</p>';
            }
        }
    } elseif (! $tables_installed) {
        $body .= '<p class="box background-pomegranate color-clouds">The database is not installed yet. '.
            'Install it from <a href="?page=utilities">Utilities</a>, and check '.
            '<a href="?page=support">Server Support</a> for diagnostics.</p>';
    } else {
        $body .= '<p class="box background-clouds">No tracker statistics yet.</p>';
    }

    require_once __DIR__.'/html.admin.layout.php';

    return view_admin_layout_html($settings, 'Dashboard', $body, 'dashboard', $csrf_token);
}
