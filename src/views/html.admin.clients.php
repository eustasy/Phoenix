<?php

declare(strict_types=1);

////	view_admin_clients_html
// Render the admin Clients page: a stacked bar per client family and a table of
// the same figures, with a metric toggle between the live swarm and the events
// ledger.
//
// The toggle is links, not buttons: each metric is a separate query, so each is
// its own URL — shareable, and the back button works.
//
// Both metrics break down by version. The ledger's versions are only as good as
// what was recorded — a long-running tracker carries labels from more than one
// era of its own detector — so a family's bar may have a versioned part and an
// unversioned remainder rather than a clean split.
//
// Marks the Clients nav active. Returns HTML string.

/**
 * @param PhoenixSettings $settings
 * @param array<string, array<string, int>> $families family => version => count
 */
function view_admin_clients_html(array $settings, string $metric, array $families, int $total, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/../functions/cdn.assets.php';
    require_once __DIR__.'/../functions/stats.client.majors.php';

    $historical = $metric === 'events';

    ////	Metric toggle
    $toggle = '';
    foreach ([
        'live' => ['share-2', 'Active peers'],
        'events' => ['clock-fading', 'All time'],
    ] as $key => [$icon, $label]) {
        $on = $metric === $key;
        $toggle .= '<a class="seg-btn'.($on ? ' is-on' : '').'" role="tab" aria-selected="'.($on ? 'true' : 'false').
            '" href="?page=clients&amp;metric='.$key.'">'.
            '<span class="ph-ico" data-lucide="'.$icon.'"></span>'.$label.'</a>';
    }
    $actions = '<div class="seg" role="tablist" aria-label="Client metric">'.$toggle.'</div>';

    if ($families === []) {
        $body = '<div class="ph-empty"><span class="ph-ico" data-lucide="users"></span>
			<p>No clients to show.</p>
			<p class="dim geo-empty-note">'.($historical
                ? 'The all-time view reads the events ledger: turn on <code>stats_enabled</code> and keep <code>completed</code> in <code>stats_events</code> to populate it.'
                : 'No peer is currently announcing to this tracker.').'</p>
		</div>';

        return view_admin_layout_html($settings, 'Clients', $body, 'clients', $csrf_token, 'Tracker', $actions, 'wide');
    }

    ////	Chart
    // Height scales with the number of bars. A fixed height squeezes a long
    // list until Chart.js starts dropping labels — and the all-time view has
    // every client the tracker has ever seen, not the dashboard's top few.
    $chart_height = max(240, count($families) * 28 + 48);

    $unit = $historical ? 'completed download' : 'peer';
    $body = '<div class="geo-toplist ph-chart-card">
			<div class="ph-traffic-head">
				<div>
					<div class="geo-metric-label">'.($historical ? 'Clients, all time' : 'Clients, active peers').'</div>
					<div class="dim geo-sub">'.number_format($total).' '.$unit.($total === 1 ? '' : 's').
                    ' across '.count($families).' client'.(count($families) === 1 ? '' : 's').'</div>
				</div>
			</div>
			<div class="ph-chart" style="height: '.$chart_height.'px"><canvas id="clients-chart"></canvas></div>
		</div>';

    ////	Table
    $rows = '';
    foreach ($families as $family => $versions) {
        $family_total = array_sum($versions);
        $share = $total > 0 ? round($family_total / $total * 100, 1) : 0.0;

        // Majors listed inline, since a column each would be mostly empty —
        // clients do not share version numbers. Each carries its exact builds
        // on hover: the major answers "how much of the swarm is on 4", and the
        // breakout answers "which 4" without spending a line on it. A family
        // whose rows carry no version (older ledger entries) has none to list.
        $parts = [];
        foreach (stats_client_majors($versions) as $major => $group) {
            if ($major === '') {
                continue;
            }

            $count = ' <span class="dim">&times;'.number_format($group['total']).'</span>';
            $label = htmlspecialchars((string) $major, ENT_QUOTES, 'UTF-8');

            // One build under a major is the major, so a tooltip repeating it
            // would say nothing.
            $builds = array_keys($group['versions']);
            if (count($builds) === 1 && (string) $builds[0] === (string) $major) {
                $parts[] = $label.$count;
                continue;
            }

            $detail_parts = [];
            foreach ($group['versions'] as $version => $version_count) {
                $detail_parts[] = $version.' ×'.number_format($version_count);
            }
            // One build per line: a family with a dozen point releases runs off
            // the screen as a single comma-separated run.
            $parts[] = '<abbr class="ph-plain" title="'.
                htmlspecialchars(implode("\n", $detail_parts), ENT_QUOTES, 'UTF-8').'">'.
                $label.'</abbr>'.$count;
        }
        $detail = $parts === [] ? '<span class="dim">&mdash;</span>' : implode(', ', $parts);

        $rows .= '<tr>'.
            '<td><span class="ph-name">'.htmlspecialchars((string) $family, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</span></td>'.
            '<td class="table-col-numeric" data-sort="'.$family_total.'">'.number_format($family_total).'</td>'.
            '<td class="table-col-numeric" data-sort="'.$share.'">'.number_format($share, 1).'%</td>'.
            '<td class="text-xs">'.$detail.'</td>'.
            '</tr>';
    }

    $sort_ico = '<span class="ph-sort-ico"><span class="ph-sort-asc ph-ico" data-lucide="chevron-up"></span><span class="ph-sort-desc ph-ico" data-lucide="chevron-down"></span></span>';
    $count = count($families);

    $body .= '<div class="ph-toolbar mt-5">
			<span class="ph-search"><span class="ph-ico" data-lucide="search"></span><input type="search" aria-label="Search clients" placeholder="Search client&hellip;" data-filter-table="#tbl-clients" data-filter-count="#clients-count"></span>
			<span class="ph-spacer"></span>
			<span class="ph-count" id="clients-count" data-noun="client">'.$count.' client'.($count === 1 ? '' : 's').'</span>
		</div>
		<div class="ph-card-table"><table id="tbl-clients">'.
        '<thead><tr>'.
            '<th class="ph-sort" data-type="text">Client '.$sort_ico.'</th>'.
            '<th class="ph-sort table-col-numeric" data-type="num" data-sort-default="desc">'.
                ($historical ? 'Downloads' : 'Peers').' '.$sort_ico.'</th>'.
            '<th class="ph-sort table-col-numeric" data-type="num">Share '.$sort_ico.'</th>'.
            '<th>Major versions</th>'.
        '</tr></thead><tbody>'.$rows.'</tbody></table></div>';

    // Same chart script as the dashboard; it draws whatever families it is
    // given, and a single '' version renders as one solid bar. Charted by major
    // rather than exact version: a family's bar otherwise fragments into a
    // sliver per point release and stops being readable.
    $chart_families = [];
    foreach ($families as $family => $versions) {
        $chart_families[$family] = [];
        foreach (stats_client_majors($versions) as $major => $group) {
            $chart_families[$family][$major] = $group['total'];
        }
    }

    $inline_js = 'var CLIENTS = '.
        (string) json_encode($chart_families, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";\n".
        (string) file_get_contents(__DIR__.'/../../public/assets/_clients.js');
    $extra_srcs = [
        '/assets/tables.js',
        cdn_assets()['chart']['url'],
    ];

    return view_admin_layout_html($settings, 'Clients', $body, 'clients', $csrf_token, 'Tracker', $actions, 'wide', '', $inline_js, $extra_srcs);
}
