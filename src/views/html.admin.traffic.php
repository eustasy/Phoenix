<?php

declare(strict_types=1);

////	view_admin_traffic_html
// Render the admin Traffic page: a time series of traffic served, and a table
// of it per torrent.
//
// The metric toggle is the point of the page. The tracker holds two different
// traffic figures and neither replaces the other:
//   * All-time — size x downloads, available for every torrent, but an estimate
//     that counts no partial and no repeat downloads.
//   * Live swarm — the uploaded/downloaded counters peers currently report.
//     Real bytes, but client-reported, reset on client restart, and gone when a
//     peer leaves.
// Both are labelled as what they are, rather than presented as one number.
//
// The chart is always the ledger-derived series — the live counters carry no
// history to plot. Marks the Traffic nav active. Returns HTML string.

/**
 * @param PhoenixSettings $settings
 * @param list<array{time: int, completions: int, bytes: int}> $series
 * @param list<array{info_hash: string, name: string|null, size: int, downloads: int, estimated: int, uploaded: int, downloaded: int, peers: int}> $torrents
 * @param array<array-key, array{days: int, bucket: int, label: string}> $windows
 *        Numeric-looking keys ('30') become int keys in PHP while 'all' stays a
 *        string, hence array-key rather than string.
 */
function view_admin_traffic_html(
    array $settings,
    array $series,
    array $torrents,
    string $metric,
    string $window,
    array $windows,
    string $csrf_token,
): string {
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/html.hash.php';
    require_once __DIR__.'/../functions/format.bytes.php';

    $peers_metric = $metric === 'peers';

    ////	Metric toggle, in the top bar — the same segmented control Geography
    // uses for its map metric.
    $toggle = '';
    foreach ([
        'events' => ['circle-check-big', 'All time'],
        'peers' => ['share-2', 'Live swarm'],
    ] as $key => [$icon, $label]) {
        $on = $metric === $key;
        $toggle .= '<a class="seg-btn'.($on ? ' is-on' : '').'" role="tab" aria-selected="'.($on ? 'true' : 'false').'"'.
            ' href="?page=traffic&amp;metric='.$key.'&amp;days='.htmlspecialchars($window, ENT_QUOTES, 'UTF-8').'">'.
            '<span class="ph-ico" data-lucide="'.$icon.'"></span>'.$label.'</a>';
    }
    $actions = '<div class="seg" role="tablist" aria-label="Traffic metric">'.$toggle.'</div>';

    ////	Chart
    $totals = 0;
    $completions = 0;
    foreach ($series as $point) {
        $totals += $point['bytes'];
        $completions += $point['completions'];
    }

    $ranges = '';
    foreach ($windows as $key => $w) {
        $ranges .= '<a class="btn btn-ghost btn-xs'.($window === $key ? ' is-on' : '').
            '" href="?page=traffic&amp;metric='.$metric.'&amp;days='.$key.'">'.
            htmlspecialchars($w['label'], ENT_QUOTES, 'UTF-8').'</a>';
    }

    $body = '<div class="geo-toplist ph-chart-card">
			<div class="ph-traffic-head">
				<div>
					<div class="geo-metric-label">Traffic served</div>
					<div class="dim geo-sub">'.format_bytes($totals).' across '.number_format($completions).
                    ' completed download'.($completions === 1 ? '' : 's').'</div>
				</div>
				<div class="row-actions">'.$ranges.'</div>
			</div>
			<div class="ph-chart ph-chart-tall"><canvas id="traffic-chart"></canvas></div>
			<p class="dim geo-foot">Estimated from the events ledger &mdash; each completed download counted as one full transfer, so partial and repeat downloads are not included.</p>
		</div>';

    if ($series === []) {
        $body = '<div class="ph-empty"><span class="ph-ico" data-lucide="chart-line"></span>
			<p>No traffic recorded for this period.</p>
			<p class="dim geo-empty-note">The chart is derived from the events ledger: turn on <code>stats_enabled</code> and keep <code>completed</code> in <code>stats_events</code> to populate it. The per-torrent figures below do not depend on it.</p>
		</div>';
    }

    ////	Per-torrent table
    if ($torrents === []) {
        $body .= '<div class="ph-empty"><span class="ph-ico" data-lucide="database"></span><p>No torrents are registered.</p></div>';
    } else {
        $rows = '';
        foreach ($torrents as $t) {
            $name = $t['name'] !== null && $t['name'] !== ''
                ? htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8')
                : '<span class="dim">&mdash;</span>';

            $primary = $peers_metric ? $t['uploaded'] : $t['estimated'];
            $rows .= '<tr>'.
                '<td><span class="ph-name">'.$name.'</span></td>'.
                '<td>'.view_hash_html($t['info_hash']).'</td>'.
                '<td class="table-col-numeric mono" data-sort="'.$primary.'">'.format_bytes($primary).'</td>'.
                '<td class="table-col-numeric mono" data-sort="'.$t['size'].'">'.($t['size'] > 0 ? format_bytes($t['size']) : '<span class="dim">&mdash;</span>').'</td>'.
                '<td class="table-col-numeric" data-sort="'.$t['downloads'].'">'.number_format($t['downloads']).'</td>'.
                '<td class="table-col-numeric" data-sort="'.$t['peers'].'">'.
                    ($t['peers'] > 0
                        ? '<a href="?page=peers&amp;info_hash='.htmlspecialchars($t['info_hash'], ENT_QUOTES, 'UTF-8').'">'.number_format($t['peers']).'</a>'
                        : '<span class="dim">0</span>').'</td>'.
                '</tr>';
        }

        $sort_ico = '<span class="ph-sort-ico"><span class="ph-sort-asc ph-ico" data-lucide="chevron-up"></span><span class="ph-sort-desc ph-ico" data-lucide="chevron-down"></span></span>';
        $count = count($torrents);

        $body .= '<div class="ph-toolbar mt-5">
			<span class="ph-search"><span class="ph-ico" data-lucide="search"></span><input type="search" aria-label="Search torrents" placeholder="Search name or hash&hellip;" data-filter-table="#tbl-traffic" data-filter-count="#traffic-count"></span>
			<span class="ph-spacer"></span>
			<span class="ph-count" id="traffic-count">'.$count.' '.($count === 1 ? 'torrent' : 'torrents').'</span>
		</div>
		<div class="ph-card-table wide"><table id="tbl-traffic">'.
            '<thead><tr>'.
                '<th class="ph-sort" data-type="text">Torrent '.$sort_ico.'</th>'.
                '<th>Hash</th>'.
                '<th class="ph-sort table-col-numeric" data-type="num" data-sort-default="desc">'.
                    ($peers_metric ? 'Uploaded' : 'Traffic').' '.$sort_ico.'</th>'.
                '<th class="ph-sort table-col-numeric" data-type="num">Size '.$sort_ico.'</th>'.
                '<th class="ph-sort table-col-numeric" data-type="num">Downloads '.$sort_ico.'</th>'.
                '<th class="ph-sort table-col-numeric" data-type="num">Peers '.$sort_ico.'</th>'.
            '</tr></thead><tbody>'.$rows.'</tbody></table></div>'.
            '<p class="dim text-sm mt-4">'.($peers_metric
                ? 'Uploaded is what the peers currently in each swarm report having sent — real bytes, but self-reported, reset when a client restarts, and gone when a peer leaves.'
                : 'Traffic is size &times; completed downloads: an estimate that counts no partial or repeat downloads.').'</p>';
    }

    // The chart data is inlined for assets/_traffic.js, like the geography map.
    $inline_js = '';
    $extra_srcs = ['/assets/tables.js'];
    if ($series !== []) {
        $extra_srcs[] = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js';
        $inline_js = 'var TRAFFIC = '.
            (string) json_encode($series, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";\n".
            'var TRAFFIC_BUCKET = '.intval($windows[$window]['bucket']).";\n".
            (string) file_get_contents(__DIR__.'/../../public/assets/_traffic.js');
    }

    return view_admin_layout_html($settings, 'Traffic', $body, 'traffic', $csrf_token, 'Tracker', $actions, 'wide', '', $inline_js, $extra_srcs);
}
