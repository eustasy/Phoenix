<?php

declare(strict_types=1);

////	view_admin_html
// Render the admin panel's Dashboard page: the tracker-statistics overview as a
// grid of stat cards plus the last-run maintenance table, and the post-install
// confirmation banner. The diagnostics, maintenance actions, and add-a-torrent
// form live on their own pages. Wrapped in the shared admin layout, which owns
// the document and the top bar; this view builds only the Dashboard body.
// Returns HTML string. Caller is responsible for echo and exit.
//
// Parameters:
//   $settings - settings array
//   $tables_installed - bool, whether all tables are installed (drives the
//                       empty-state message when there are no stats yet)
//   $show_installed - bool, whether to show the "Installation complete" banner
//   $csrf_token - string, per-session token for the layout's logout form
//   $stats - array<string,int>|false, merged tracker stats (seeders, leechers,
//            peers, torrents, downloads, traffic) plus 'registered' (total
//            torrents). False hides the stats block (e.g. tables not installed).
//   $tasks - maintenance task name => {value: last-run Unix timestamp, source:
//            who ran it ('admin'|'cron'|'auto', '' if pre-source-tracking)}.

/**
 * @param PhoenixSettings $settings
 * @param array<string, int>|false $stats
 * @param array<string, array{value: int, source: string}> $tasks
 */
function view_admin_html(array $settings, bool $tables_installed, bool $show_installed = false, string $csrf_token = '', array|false $stats = false, array $tasks = []): string
{
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/../functions/format.bytes.php';

    $body = '';

    if ($show_installed) {
        $body .= '<div class="alert alert-success alert-center"><span class="ph-ico" data-lucide="check-circle-2"></span><strong>Installation complete.</strong>&nbsp;Your tracker is live and accepting announces.</div>';
    }

    if ($stats !== false) {
        $registered = $stats['registered'] ?? 0;
        $top_margin = $show_installed ? ' mt-5' : '';

        $body .= '<div class="ph-stat-grid'.$top_margin.'">
			<div class="ph-stat ph-stat-blue">
				<div class="ph-stat-top"><div class="ph-stat-value">'.number_format($stats['peers']).'</div><div class="ph-stat-ico"><span class="ph-ico" data-lucide="share-2"></span></div></div>
				<div class="ph-stat-label">Active peers</div>
				<div class="ph-stat-sub"><b>'.number_format($stats['seeders']).'</b> seeders &middot; <b>'.number_format($stats['leechers']).'</b> leechers</div>
			</div>
			<div class="ph-stat ph-stat-purple">
				<div class="ph-stat-top"><div class="ph-stat-value">'.number_format($registered).'</div><div class="ph-stat-ico tint-purple"><span class="ph-ico" data-lucide="database"></span></div></div>
				<div class="ph-stat-label">Registered torrents</div>
				<div class="ph-stat-sub"><b>'.number_format($stats['torrents']).'</b> with active peers</div>
			</div>
			<div class="ph-stat ph-stat-green">
				<div class="ph-stat-top"><div class="ph-stat-value">'.number_format($stats['downloads']).'</div><div class="ph-stat-ico"><span class="ph-ico" data-lucide="circle-check-big"></span></div></div>
				<div class="ph-stat-label">Completed downloads</div>
				<div class="ph-stat-sub">All-time</div>
			</div>
			<div class="ph-stat ph-stat-orange">
				<div class="ph-stat-top"><div class="ph-stat-value">'.format_bytes($stats['traffic']).'</div><div class="ph-stat-ico"><span class="ph-ico" data-lucide="arrow-up-down"></span></div></div>
				<div class="ph-stat-label">Traffic served</div>
				<div class="ph-stat-sub mono">'.number_format($stats['traffic']).' bytes</div>
			</div>
		</div>';

        // Last-run timestamp for each maintenance task that has ever run.
        $task_labels = [
            'install' => ['wand-2', 'Installed'],
            'migrate' => ['git-merge', 'Migrated'],
            'clean' => ['brush-cleaning', 'Cleaned'],
            'optimize' => ['gauge', 'Optimized'],
            'backup' => ['archive', 'Backed up'],
        ];
        $rows = '';
        foreach ($task_labels as $task_name => [$icon, $label]) {
            if (isset($tasks[$task_name])) {
                $run = $tasks[$task_name];
                $by = $run['source'] !== ''
                    ? '<span class="badge">'.htmlspecialchars(ucfirst($run['source']), ENT_QUOTES, 'UTF-8').'</span>'
                    : '<span class="dim">&mdash;</span>';
                $rows .= '<tr><td><span class="flex items-center gap-2"><span class="ph-ico ph-li-ico" data-lucide="'.$icon.'"></span>'.$label.'</span></td>'.
                    '<td class="mono muted">'.date('Y-m-d H:i', $run['value']).'</td>'.
                    '<td>'.$by.'</td>'.
                    '<td class="table-col-numeric"><span class="badge badge-green">done</span></td></tr>';
            }
        }
        if ($rows !== '') {
            $body .= '<div class="ph-section-head"><h3>Maintenance</h3><a class="btn btn-ghost btn-sm" href="?page=utilities">Run tasks<span class="ph-ico" data-lucide="arrow-right"></span></a></div>
		<div class="ph-card-table">
			<table>
				<thead><tr><th>Task</th><th>Last run</th><th>By</th><th class="table-col-numeric">Status</th></tr></thead>
				<tbody>'.$rows.'</tbody>
			</table>
		</div>';
        }
    } elseif (! $tables_installed) {
        $body .= '<div class="alert alert-danger"><span class="ph-ico" data-lucide="triangle-alert"></span><div>The database is not installed yet. Install it from <a href="?page=utilities">Utilities</a>, and check <a href="?page=support">Server Support</a> for diagnostics.</div></div>';
    } else {
        $body .= '<div class="ph-empty"><span class="ph-ico" data-lucide="bar-chart-3"></span><p>No tracker statistics yet.</p></div>';
    }

    $actions = '<a class="btn btn-secondary btn-sm" href="?page=support"><span class="ph-ico" data-lucide="stethoscope"></span>Diagnostics</a>'.
        '<a class="btn btn-primary btn-sm" href="?page=add"><span class="ph-ico" data-lucide="plus"></span>Add Torrent</a>';

    return view_admin_layout_html($settings, 'Dashboard', $body, 'dashboard', $csrf_token, 'Tracker', $actions);
}
