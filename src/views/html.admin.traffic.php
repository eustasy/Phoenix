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
//   * Active peers — the uploaded/downloaded counters peers currently report.
//     Real bytes, but client-reported, reset on client restart, and gone when a
//     peer leaves.
// Both are labelled as what they are, rather than presented as one number.
//
// The chart follows the metric. All-time gets the ledger-derived time series;
// the live swarm gets the busiest peers as paired upload/download bars, because
// those counters are cumulative-since-client-start and vanish when a peer
// leaves — there is no history to plot, and showing the historical chart under
// a live metric claimed something the tracker cannot know.
//
// The table below is one page of a searched, sorted listing — $total is what
// the filter matched, not what was rendered, so the pager knows there is a next
// page. ?info_hash narrows it to one torrent, which is how a row elsewhere
// links here for that torrent's traffic.
//
// Marks the Traffic nav active. Returns HTML string.

/**
 * @param PhoenixSettings $settings
 * @param list<array{time: int, completions: int, bytes: int}> $series
 * @param list<array{info_hash: string, name: string|null, filename: string|null, user: string|null, size: int, downloads: int, estimated: int, uploaded: int, downloaded: int, peers: int}> $torrents
 * @param array<array-key, array{days: int, bucket: int, label: string}> $windows
 *        Numeric-looking keys ('30') become int keys in PHP while 'all' stays a
 *        string, hence array-key rather than string.
 * @param list<array{label: string, client: string, torrent: string|null, uploaded: int, downloaded: int}> $swarm
 */
function view_admin_traffic_html(
    array $settings,
    array $series,
    array $torrents,
    string $metric,
    string $window,
    array $windows,
    string $csrf_token,
    array $swarm = [],
    int $total = 0,
    int $offset = 0,
    int $limit = 100,
    string $search = '',
    string $info_hash = '',
    string $sort = 'traffic',
    string $dir = 'desc',
): string {
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/../functions/cdn.assets.php';
    require_once __DIR__.'/html.hash.php';
    require_once __DIR__.'/html.filename.php';
    require_once __DIR__.'/../functions/format.bytes.php';

    $peers_metric = $metric === 'peers';

    // Every link out of the table carries the metric, the window and the current
    // filter, so sorting a search does not silently drop back to the whole
    // table — or to the other metric. Defaults stay out of the query string.
    $query = static function (array $overrides) use ($metric, $window, $search, $info_hash, $sort, $dir, $offset): string {
        $params = [
            'page' => 'traffic',
            'metric' => $metric,
            'days' => $window,
            'offset' => $offset > 0 ? (string) $offset : null,
        ];
        if ($search !== '') {
            $params['q'] = $search;
        }
        if ($info_hash !== '') {
            $params['info_hash'] = $info_hash;
        }
        if ($sort !== 'traffic' || $dir !== 'desc') {
            $params['sort'] = $sort;
            $params['dir'] = $dir;
        }

        return '?'.htmlspecialchars(http_build_query(array_filter(
            array_merge($params, $overrides),
            static fn (mixed $v): bool => $v !== null && $v !== '',
        )), ENT_QUOTES, 'UTF-8');
    };

    // A sortable header: clicking the active column flips direction, any other
    // column starts descending — the useful default for figures.
    $sort_link = static function (string $key, string $label) use ($sort, $dir, $query): string {
        $active = $sort === $key;
        $next = $active && $dir === 'desc' ? 'asc' : 'desc';
        $ico = $active
            ? '<span class="ph-sort-ico"><span class="ph-ico" data-lucide="chevron-'.($dir === 'asc' ? 'up' : 'down').'"></span></span>'
            : '';

        return '<a class="ph-sort-link'.($active ? ' is-on' : '').'" href="'.
            $query(['sort' => $key, 'dir' => $next, 'offset' => null]).'">'.$label.$ico.'</a>';
    };

    ////	Metric toggle, in the top bar — the same segmented control Geography
    // uses for its map metric.
    $toggle = '';
    foreach ([
        'peers' => ['share-2', 'Active peers'],
        'events' => ['clock-fading', 'All time'],
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
        // Cast: a numeric-looking key ('90') is an int by the time it is read
        // back out of the array, so a strict compare against the string $window
        // never matched and no window was ever marked selected.
        $ranges .= '<a class="btn btn-ghost btn-xs'.((string) $key === $window ? ' is-on' : '').
            '" href="?page=traffic&amp;metric='.$metric.'&amp;days='.$key.'">'.
            htmlspecialchars($w['label'], ENT_QUOTES, 'UTF-8').'</a>';
    }

    if ($peers_metric) {
        // No window buttons: there is no series to window.
        $up = 0;
        $down = 0;
        foreach ($swarm as $peer) {
            $up += $peer['uploaded'];
            $down += $peer['downloaded'];
        }

        $body = $swarm === []
            ? '<div class="ph-empty"><span class="ph-ico" data-lucide="share-2"></span>
				<p>No peer is reporting any transfer.</p>
				<p class="dim geo-empty-note">Peers report their own cumulative totals on announce; a swarm that has only just formed has nothing to show yet.</p>
			</div>'
            : '<div class="geo-toplist ph-chart-card">
				<div class="ph-traffic-head">
					<div>
						<div class="geo-metric-label">Busiest peers</div>
						<div class="dim geo-sub">'.format_bytes($up).' up &middot; '.format_bytes($down).' down, across the '.count($swarm).' busiest</div>
					</div>
				</div>
				<div class="ph-chart ph-chart-tall"><canvas id="swarm-chart"></canvas></div>
				<p class="dim geo-foot">Reported by the peers themselves &mdash; cumulative since each client started, reset when it restarts, and gone when the peer leaves. A snapshot of the live swarm, not a historical ledger.</p>
			</div>';
    } else {
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
			<p class="dim geo-foot">Estimated from the events ledger &mdash; each completed download counted as one full transfer, so partial and repeat downloads are not included. <a href="?page=geography&amp;metric=traffic">See it by country</a>.</p>
		</div>';

        if ($series === []) {
            $body = '<div class="ph-empty"><span class="ph-ico" data-lucide="chart-line"></span>
				<p>No traffic recorded for this period.</p>
				<p class="dim geo-empty-note">The chart is derived from the events ledger: turn on <code>stats_enabled</code> and keep <code>completed</code> in <code>stats_events</code> to populate it. The per-torrent figures below do not depend on it.</p>
			</div>';
        }
    }

    ////	Per-torrent table
    if ($torrents === [] && ($search !== '' || $info_hash !== '')) {
        $body .= '<div class="ph-empty"><span class="ph-ico" data-lucide="search-x"></span><p>No torrents match this filter. '.
            '<a href="'.$query(['q' => null, 'info_hash' => null, 'offset' => null]).'">Show all torrents</a></p></div>';
    } elseif ($torrents === []) {
        $body .= '<div class="ph-empty"><span class="ph-ico" data-lucide="database"></span><p>No torrents are registered.</p></div>';
    } else {
        $rows = '';
        foreach ($torrents as $t) {
            $name = $t['name'] !== null && $t['name'] !== ''
                ? htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8')
                : '<span class="dim">&mdash;</span>';

            $file = view_filename_html($t['filename'], 'dim');

            $owner = $t['user'] === null || $t['user'] === ''
                ? '<span class="dim">&mdash;</span>'
                : '<span class="badge badge-cyan">'.htmlspecialchars($t['user'], ENT_QUOTES, 'UTF-8').'</span>';

            $primary = $peers_metric ? $t['uploaded'] : $t['estimated'];
            $rows .= '<tr>'.
                '<td><span class="ph-name">'.$name.'</span></td>'.
                '<td>'.$file.'</td>'.
                '<td>'.$owner.'</td>'.
                '<td>'.view_hash_html($t['info_hash']).'</td>'.
                '<td class="table-col-numeric mono">'.format_bytes($primary).'</td>'.
                '<td class="table-col-numeric mono">'.($t['size'] > 0 ? format_bytes($t['size']) : '<span class="dim">&mdash;</span>').'</td>'.
                '<td class="table-col-numeric">'.number_format($t['downloads']).'</td>'.
                '<td class="table-col-numeric">'.
                    ($t['peers'] > 0
                        ? '<a href="?page=peers&amp;info_hash='.htmlspecialchars($t['info_hash'], ENT_QUOTES, 'UTF-8').'">'.number_format($t['peers']).'</a>'
                        : '<span class="dim">0</span>').'</td>'.
                '</tr>';
        }

        // Page window summary + prev/next paging. This branch only runs with
        // rows in hand, so there is always a window to describe.
        $last = $offset + count($torrents);
        $window_text = 'Showing '.number_format($offset + 1).'&ndash;'.number_format($last).' of '.number_format($total);

        $pager = '';
        if ($offset > 0 || $last < $total) {
            $prev = $offset > 0
                ? '<a class="btn btn-ghost btn-sm" href="'.$query(['offset' => max(0, $offset - $limit)]).'"><span class="ph-ico" data-lucide="arrow-left"></span>Previous</a>'
                : '<span class="btn btn-ghost btn-sm" aria-disabled="true"><span class="ph-ico" data-lucide="arrow-left"></span>Previous</span>';
            $next = $last < $total
                ? '<a class="btn btn-ghost btn-sm" href="'.$query(['offset' => $offset + $limit]).'">Next<span class="ph-ico" data-lucide="arrow-right"></span></a>'
                : '<span class="btn btn-ghost btn-sm" aria-disabled="true">Next<span class="ph-ico" data-lucide="arrow-right"></span></span>';
            $pager = '<div class="flex items-center gap-2 justify-end mt-4">'.$prev.$next.'</div>';
        }

        // When narrowed to one torrent, say which — and offer the way back out.
        $filter_banner = '';
        if ($info_hash !== '') {
            $first = $torrents[0];
            $label = $first['name'] !== null && $first['name'] !== ''
                ? htmlspecialchars($first['name'], ENT_QUOTES, 'UTF-8')
                : '<span class="mono">'.htmlspecialchars(substr($info_hash, 0, 12), ENT_QUOTES, 'UTF-8').'&hellip;</span>';
            $filter_banner = '<div class="alert alert-info mt-5"><span class="ph-ico" data-lucide="filter"></span><div>'.
                'Traffic for <b>'.$label.'</b> '.
                '<a href="'.$query(['info_hash' => null, 'offset' => null]).'">Show all torrents</a></div></div>';
        }

        // A GET form, not a client-side filter: the table is paged, so filtering
        // in the browser would only ever search the rendered page. The metric,
        // window and sort ride along as hidden fields so a search does not reset
        // the view the reader had chosen.
        $sort_state = '';
        if ($sort !== 'traffic' || $dir !== 'desc') {
            $sort_state = '<input type="hidden" name="sort" value="'.htmlspecialchars($sort, ENT_QUOTES, 'UTF-8').'">'.
                '<input type="hidden" name="dir" value="'.htmlspecialchars($dir, ENT_QUOTES, 'UTF-8').'">';
        }

        $body .= $filter_banner.'<form method="GET" action="" class="ph-toolbar mt-5">
			<input type="hidden" name="page" value="traffic">
			<input type="hidden" name="metric" value="'.htmlspecialchars($metric, ENT_QUOTES, 'UTF-8').'">
			<input type="hidden" name="days" value="'.htmlspecialchars($window, ENT_QUOTES, 'UTF-8').'">
			'.($info_hash !== '' ? '<input type="hidden" name="info_hash" value="'.htmlspecialchars($info_hash, ENT_QUOTES, 'UTF-8').'">' : '').'
			'.$sort_state.'
			<span class="ph-search"><span class="ph-ico" data-lucide="search"></span><input type="search" name="q" value="'.htmlspecialchars($search, ENT_QUOTES, 'UTF-8').'" aria-label="Search torrents" placeholder="Search name, filename, owner, hash&hellip;"></span>
			<button class="btn btn-secondary btn-sm" type="submit">Search</button>'.
            ($search !== '' || $info_hash !== ''
                ? '<a class="btn btn-ghost btn-sm" href="'.$query(['q' => null, 'info_hash' => null, 'offset' => null]).'">Clear</a>'
                : '').'
			<span class="ph-spacer"></span>
			<span class="dim text-sm">'.$window_text.'</span>
		</form>
		<div class="ph-card-table wide"><table id="tbl-traffic">'.
            '<thead><tr>'.
                '<th>'.$sort_link('name', 'Torrent').'</th>'.
                '<th>'.$sort_link('filename', 'Filename').'</th>'.
                '<th>'.$sort_link('user', 'Owner').'</th>'.
                '<th>Hash</th>'.
                '<th class="table-col-numeric">'.$sort_link('traffic', $peers_metric ? 'Uploaded' : 'Traffic').'</th>'.
                '<th class="table-col-numeric">'.$sort_link('size', 'Size').'</th>'.
                '<th class="table-col-numeric">'.$sort_link('downloads', 'Downloads').'</th>'.
                '<th class="table-col-numeric">'.$sort_link('peers', 'Peers').'</th>'.
            '</tr></thead><tbody>'.$rows.'</tbody></table></div>'.$pager.
            '<p class="dim text-sm mt-4">'.($peers_metric
                ? 'Uploaded is what the peers currently in each swarm report having sent — real bytes, but self-reported, reset when a client restarts, and gone when a peer leaves.'
                : 'Traffic is size &times; completed downloads: an estimate that counts no partial or repeat downloads.').'</p>';
    }

    // Whichever chart the metric calls for, inlined with its data like the
    // geography map. Only one is ever loaded.
    $inline_js = '';
    // tables.js went with the client-side filter and sort it drove; the hash
    // cells still want copy.js.
    $extra_srcs = ['/assets/copy.js'];
    if ($peers_metric && $swarm !== []) {
        $extra_srcs[] = cdn_assets()['chart']['url'];
        $inline_js = 'var SWARM = '.
            (string) json_encode($swarm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";\n".
            (string) file_get_contents(__DIR__.'/../../public/assets/_swarm.js');
    } elseif (! $peers_metric && $series !== []) {
        $extra_srcs[] = cdn_assets()['chart']['url'];
        $inline_js = 'var TRAFFIC = '.
            (string) json_encode($series, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";\n".
            'var TRAFFIC_BUCKET = '.intval($windows[$window]['bucket']).";\n".
            (string) file_get_contents(__DIR__.'/../../public/assets/_traffic.js');
    }

    return view_admin_layout_html($settings, 'Traffic', $body, 'traffic', $csrf_token, 'Tracker', $actions, 'wide', '', $inline_js, $extra_srcs);
}
