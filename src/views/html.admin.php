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
 * @param array<string, list<array{info_hash: string, name: string|null, seeders: int, leechers: int, downloads: int, traffic: int}>> $torrent_cards
 * @param array<string, array<string, int>> $count_cards
 */
function view_admin_html(array $settings, bool $tables_installed, bool $show_installed = false, string $csrf_token = '', array|false $stats = false, array $tasks = [], array $torrent_cards = [], array $count_cards = []): string
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

    ////	Mini-tables
    // Ranked cards, three columns each, linking into the listing they
    // summarise. A card with no rows is dropped rather than shown empty — a
    // tracker with no unhealthy swarms should not be told about it every visit.
    if ($torrent_cards !== [] || $count_cards !== []) {
        require_once __DIR__.'/html.toplist.php';

        $hash_link = static fn (string $hash): string => '?page=peers&amp;info_hash='.htmlspecialchars($hash, ENT_QUOTES, 'UTF-8');
        $torrent_label = static fn (array $t): string => $t['name'] !== null && $t['name'] !== ''
            ? $t['name']
            : substr($t['info_hash'], 0, 12).'…';

        // Bars are proportional to the leader of each card, so a card is read
        // against itself rather than against the tracker's busiest swarm.
        $rows_from = static function (array $torrents, string $key, callable $format) use ($hash_link, $torrent_label): array {
            $max = 0;
            foreach ($torrents as $t) {
                $max = max($max, (int) $t[$key]);
            }
            $rows = [];
            foreach ($torrents as $t) {
                $rows[] = [
                    'label' => $torrent_label($t),
                    'value' => $format($t),
                    'bar' => $max > 0 ? (int) round((int) $t[$key] / $max * 100) : 0,
                    'href' => $hash_link($t['info_hash']),
                ];
            }

            return $rows;
        };

        // A label => count map (clients, countries) as ranked rows, top 5.
        $rank_rows = static function (array $counts, ?string $href): array {
            $counts = array_slice($counts, 0, 5, true);
            $max = $counts === [] ? 0 : max($counts);
            $rows = [];
            foreach ($counts as $label => $n) {
                $rows[] = [
                    'label' => (string) $label,
                    'value' => number_format((int) $n),
                    'bar' => $max > 0 ? (int) round((int) $n / $max * 100) : 0,
                    'href' => $href,
                ];
            }

            return $rows;
        };

        $panels = [];
        if (! empty($torrent_cards['seeded'])) {
            $panels[] = view_toplist_html(
                'Most seeded',
                $rows_from($torrent_cards['seeded'], 'seeders', static fn (array $t): string => number_format($t['seeders']).' seeders'),
                '#66800b',
                ['label' => 'All torrents', 'href' => '?page=torrents'],
            );
        }
        if (! empty($torrent_cards['leeched'])) {
            $panels[] = view_toplist_html(
                'Most leeched',
                $rows_from($torrent_cards['leeched'], 'leechers', static fn (array $t): string => number_format($t['leechers']).' leechers'),
                '#205ea6',
                ['label' => 'All peers', 'href' => '?page=peers&amp;state=0'],
            );
        }
        if (! empty($torrent_cards['trouble'])) {
            $panels[] = view_toplist_html(
                'Torrents in trouble',
                $rows_from($torrent_cards['trouble'], 'leechers', static fn (array $t): string => number_format($t['seeders']).'S / '.number_format($t['leechers']).'L'),
                '#af3029',
                null,
            );
        }
        if (! empty($torrent_cards['traffic'])) {
            $panels[] = view_toplist_html(
                'Most traffic served',
                $rows_from($torrent_cards['traffic'], 'traffic', static fn (array $t): string => format_bytes($t['traffic'])),
                '#bc5215',
                null,
            );
        }
        if (! empty($count_cards['clients'])) {
            $panels[] = view_toplist_html('Top clients', $rank_rows($count_cards['clients'], null), '#5e409d');
        }
        if (! empty($count_cards['countries'])) {
            $panels[] = view_toplist_html(
                'Top countries',
                $rank_rows($count_cards['countries'], '?page=geography'),
                '#24837b',
                ['label' => 'Geography', 'href' => '?page=geography'],
            );
        }

        if ($panels !== []) {
            $body .= '<div class="ph-section-head"><h3>At a glance</h3></div>'.
                '<div class="ph-toplist-grid">'.implode('', $panels).'</div>';
        }
    }

    $actions = '<a class="btn btn-secondary btn-sm" href="?page=support"><span class="ph-ico" data-lucide="stethoscope"></span>Diagnostics</a>'.
        '<a class="btn btn-primary btn-sm" href="?page=add"><span class="ph-ico" data-lucide="plus"></span>Add Torrent</a>';

    return view_admin_layout_html($settings, 'Dashboard', $body, 'dashboard', $csrf_token, 'Tracker', $actions);
}
