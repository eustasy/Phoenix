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
 * @param array<string, list<array{info_hash: string, name: string|null, filename: string|null, seeders: int, leechers: int, downloads: int, traffic: int}>> $torrent_cards
 * @param array<string, array<string, int>> $count_cards
 * @param array<string, list<array{address: string, peer_id: string, info_hash: string, name: string|null, bytes: int}>> $peer_cards
 * @param array<string, array<string, int>> $clients client family => version => peers
 * @param list<array{time: int, completions: int, bytes: int}> $bandwidth
 */
function view_admin_html(array $settings, bool $tables_installed, bool $show_installed = false, string $csrf_token = '', array|false $stats = false, array $tasks = [], array $torrent_cards = [], array $count_cards = [], array $peer_cards = [], array $clients = [], array $bandwidth = []): string
{
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/../functions/cdn.assets.php';
    require_once __DIR__.'/../functions/format.bytes.php';
    require_once __DIR__.'/../functions/stats.client.majors.php';

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
				<div class="ph-stat-top"><div class="ph-stat-value">'.format_bytes($stats['bandwidth']).'</div><div class="ph-stat-ico"><span class="ph-ico" data-lucide="arrow-up-down"></span></div></div>
				<div class="ph-stat-label">Bandwidth served</div>
				<div class="ph-stat-sub mono">'.number_format($stats['bandwidth']).' bytes</div>
			</div>
		</div>';

        // Last-run timestamp for each maintenance task that has ever run.
        $task_labels = [
            'install' => ['wand-2', 'Installed'],
            'migrate' => ['git-merge', 'Migrated'],
            'clean' => ['brush-cleaning', 'Pruned'],
            'analyze' => ['gauge', 'Analyzed'],
            'optimize' => ['chart-no-axes-column', 'Optimized'],
            'check' => ['shield-check', 'Checked'],
            'backup' => ['archive', 'Backed up'],
        ];
        // Matches the Task History page: cron and auto are the expected
        // background noise, anything else is coloured to catch the eye.
        $source_badge = static function (string $source): string {
            if ($source === '') {
                return '<span class="dim">&mdash;</span>';
            }
            $class = match ($source) {
                'cron', 'auto' => 'badge',
                'admin' => 'badge badge-blue',
                default => 'badge badge-yellow',
            };

            return '<span class="'.$class.'">'.htmlspecialchars(ucfirst($source), ENT_QUOTES, 'UTF-8').'</span>';
        };

        $rows = '';
        foreach ($task_labels as $task_name => [$icon, $label]) {
            if (isset($tasks[$task_name])) {
                $run = $tasks[$task_name];
                $by = $source_badge($run['source']);
                $rows .= '<tr><td><span class="flex items-center gap-2"><span class="ph-ico ph-li-ico" data-lucide="'.$icon.'"></span>'.$label.'</span></td>'.
                    '<td class="mono muted">'.date('Y-m-d H:i', $run['value']).'</td>'.
                    '<td>'.$by.'</td>'.
                    '<td class="table-col-numeric"><span class="badge badge-green">done</span></td></tr>';
            }
        }
        if ($rows !== '') {
            $body .= '<div class="ph-section-head"><h3>Maintenance</h3><div class="row-actions"><a class="btn btn-ghost btn-sm" href="?page=tasks">History<span class="ph-ico" data-lucide="history"></span></a><a class="btn btn-ghost btn-sm" href="?page=utilities">Run tasks<span class="ph-ico" data-lucide="arrow-right"></span></a></div></div>
		<div class="ph-card-table">
			<table>
				<thead><tr><th>Task</th><th>Last run</th><th>By</th><th class="table-col-numeric">Status</th></tr></thead>
				<tbody>'.$rows.'</tbody>
			</table>
		</div>';
        }
    } elseif (! $tables_installed) {
        $body .= '<div class="alert alert-danger"><span class="ph-ico" data-lucide="triangle-alert"></span><div>The database is not installed yet. Install it from <a href="?page=utilities">DB Utilities</a>, and check <a href="?page=support">Server Support</a> for diagnostics.</div></div>';
    } else {
        $body .= '<div class="ph-empty"><span class="ph-ico" data-lucide="bar-chart-3"></span><p>No tracker statistics yet.</p></div>';
    }

    ////	Mini-tables
    // Ranked cards, three columns each, linking into the listing they
    // summarise. A card with no rows is dropped rather than shown empty — a
    // tracker with no unhealthy swarms should not be told about it every visit.
    if ($torrent_cards !== [] || $count_cards !== [] || $peer_cards !== [] || $clients !== [] || $bandwidth !== []) {
        require_once __DIR__.'/html.toplist.php';

        // A row links into the view that answers the question its card asked.
        // Most cards rank torrents by their swarm, so they land on the peers
        // drill-down; the bandwidth card ranks bytes, so it lands on Bandwidth's.
        $hash_link = static fn (string $hash, string $page = 'peers'): string =>
            '?page='.$page.'&amp;info_hash='.htmlspecialchars($hash, ENT_QUOTES, 'UTF-8');
        $torrent_label = static fn (array $t): string => $t['name'] !== null && $t['name'] !== ''
            ? $t['name']
            : substr($t['info_hash'], 0, 12).'…';

        // A card row shows one line, and the display name is not an identifier:
        // the same release rebuilt carries the same name under a different hash.
        // The hash and filename go in the tooltip rather than the label.
        $torrent_title = static function (array $t): string {
            $title = $t['info_hash'];
            if (($t['filename'] ?? null) !== null && $t['filename'] !== '') {
                $title .= "\n".$t['filename'];
            }

            return $title;
        };

        // Bars are proportional to the leader of each card, so a card is read
        // against itself rather than against the tracker's busiest swarm.
        $rows_from = static function (array $torrents, string $key, callable $format, string $page = 'peers') use ($hash_link, $torrent_label, $torrent_title): array {
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
                    'href' => $hash_link($t['info_hash'], $page),
                    'title' => $torrent_title($t),
                ];
            }

            return $rows;
        };

        // A label => count map (clients, countries) as ranked rows, top 5.
        // Sorted here rather than trusted from the caller: peers_geo_counts()
        // returns whatever order it resolved addresses in, so slicing an
        // unsorted map would show five arbitrary countries, not the top five.
        $rank_rows = static function (array $counts): array {
            arsort($counts);
            $counts = array_slice($counts, 0, 5, true);
            $max = $counts === [] ? 0 : max($counts);
            $rows = [];
            foreach ($counts as $label => $n) {
                $rows[] = [
                    'label' => (string) $label,
                    'value' => number_format((int) $n),
                    'bar' => $max > 0 ? (int) round((int) $n / $max * 100) : 0,
                    // No per-row link: every row would point at the same page,
                    // which the card's footer link already covers.
                    'href' => null,
                ];
            }

            return $rows;
        };

        // A peer's address is what the listing filters on, so a row links to
        // every swarm that peer is in.
        $query_peer = static fn (string $address): string => '?page=peers&amp;q='.rawurlencode($address);

        $panels = [];
        if (! empty($torrent_cards['seeded'])) {
            $panels[] = view_toplist_html(
                'Most seeded',
                $rows_from($torrent_cards['seeded'], 'seeders', static fn (array $t): string => number_format($t['seeders']).' seeders'),
                '#66800b',
                ['label' => 'All torrents', 'href' => '?page=torrents&amp;sort=seeders'],
            );
        }
        if (! empty($torrent_cards['leeched'])) {
            $panels[] = view_toplist_html(
                'Most leeched',
                $rows_from($torrent_cards['leeched'], 'leechers', static fn (array $t): string => number_format($t['leechers']).' leechers'),
                '#205ea6',
                ['label' => 'All torrents', 'href' => '?page=torrents&amp;sort=leechers'],
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
        if (! empty($torrent_cards['bandwidth'])) {
            $panels[] = view_toplist_html(
                'Most bandwidth served',
                $rows_from($torrent_cards['bandwidth'], 'bandwidth', static fn (array $t): string => format_bytes($t['bandwidth']), 'bandwidth'),
                '#bc5215',
                ['label' => 'All bandwidth', 'href' => '?page=bandwidth&amp;metric=events'],
            );
        }
        // Peers ranked by bytes moved — distinct from the torrent cards above,
        // which rank torrents by how many peers they have.
        $peer_rows = static function (array $peers) use ($query_peer): array {
            $max = 0;
            foreach ($peers as $p) {
                $max = max($max, $p['bytes']);
            }
            $rows = [];
            foreach ($peers as $p) {
                $rows[] = [
                    'label' => $p['address'],
                    'value' => format_bytes($p['bytes']),
                    'bar' => $max > 0 ? (int) round($p['bytes'] / $max * 100) : 0,
                    'href' => $query_peer($p['address']),
                ];
            }

            return $rows;
        };

        if (! empty($peer_cards['seeders'])) {
            $panels[] = view_toplist_html(
                'Top seeders',
                $peer_rows($peer_cards['seeders']),
                '#66800b',
                ['label' => 'All seeders', 'href' => '?page=peers&amp;state=1&amp;sort=uploaded'],
            );
        }
        if (! empty($peer_cards['leechers'])) {
            $panels[] = view_toplist_html(
                'Top leechers',
                $peer_rows($peer_cards['leechers']),
                '#205ea6',
                ['label' => 'All leechers', 'href' => '?page=peers&amp;state=0&amp;sort=downloaded'],
            );
        }
        if (! empty($count_cards['countries'])) {
            $panels[] = view_toplist_html(
                'Top countries',
                $rank_rows($count_cards['countries']),
                '#24837b',
                ['label' => 'Geography', 'href' => '?page=geography'],
            );
        }

        // Charts lead the section, two to a row. The right slot is reserved for
        // traffic over time; until that exists the client chart simply sits in
        // the left half rather than stretching across.
        $charts = '';
        if ($clients !== []) {
            $charts .= '<div class="geo-toplist ph-chart-card"><h3>Current clients</h3>'.
                '<div class="ph-chart"><canvas id="clients-chart"></canvas></div>'.
                // Metric named rather than left to the default: the chart is
                // the live swarm, and the link should still land on it if the
                // page's default ever moves.
                '<div class="ph-toplist-more"><a href="?page=clients&amp;metric=live">All clients</a></div>'.
                '</div>';
        }

        if ($bandwidth !== []) {
            $bytes = 0;
            foreach ($bandwidth as $point) {
                $bytes += $point['bytes'];
            }
            // Same canvas id the Bandwidth page uses, so the shared chart script
            // needs no argument to find it.
            $charts .= '<div class="geo-toplist ph-chart-card"><h3>Bandwidth &mdash; last 30 days</h3>'.
                '<div class="ph-chart"><canvas id="bandwidth-chart"></canvas></div>'.
                // Carries the metric: this card is the ledger-derived series,
                // and the Bandwidth page opens on the live swarm.
                '<div class="ph-toplist-more"><a href="?page=bandwidth&amp;metric=events">'.format_bytes($bytes).' served &middot; all traffic</a></div>'.
                '</div>';
        }

        if ($charts !== '' || $panels !== []) {
            $body .= '<div class="ph-section-head"><h3>At a glance</h3></div>';
        }
        if ($charts !== '') {
            $body .= '<div class="ph-chart-grid">'.$charts.'</div>';
        }
        if ($panels !== []) {
            $body .= '<div class="ph-toplist-grid">'.implode('', $panels).'</div>';
        }
    }

    $actions = '<a class="btn btn-secondary btn-sm" href="?page=support"><span class="ph-ico" data-lucide="stethoscope"></span>Diagnostics</a>'.
        '<a class="btn btn-primary btn-sm" href="?page=add"><span class="ph-ico" data-lucide="plus"></span>Add Torrent</a>';

    // The chart logic lives in assets/_clients.js; it is read in and emitted
    // inline (prefixed with the PHP-computed breakdown) so the data is in
    // scope — hence the "_" name marking it an inlined file.
    $inline_js = '';
    $extra_srcs = [];
    if ($clients !== [] || $bandwidth !== []) {
        $extra_srcs[] = cdn_assets()['chart']['url'];
    }
    if ($clients !== []) {
        // Charted by major rather than exact version, as the Clients page is:
        // a family's bar otherwise fragments into a sliver per point release.
        $chart_clients = [];
        foreach ($clients as $family => $versions) {
            $chart_clients[$family] = [];
            foreach (stats_client_majors($versions) as $major => $group) {
                $chart_clients[$family][$major] = $group['total'];
            }
        }

        $inline_js .= 'var CLIENTS = '.
            (string) json_encode($chart_clients, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";\n".
            (string) file_get_contents(__DIR__.'/../../public/assets/_clients.js')."\n";
    }
    if ($bandwidth !== []) {
        $inline_js .= 'var TRAFFIC = '.
            (string) json_encode($bandwidth, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";\n".
            'var TRAFFIC_BUCKET = 86400;'."\n".
            (string) file_get_contents(__DIR__.'/../../public/assets/_traffic.js');
    }

    return view_admin_layout_html($settings, 'Dashboard', $body, 'dashboard', $csrf_token, 'Tracker', $actions, '', '', $inline_js, $extra_srcs);
}
