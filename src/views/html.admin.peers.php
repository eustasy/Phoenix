<?php

declare(strict_types=1);

////	view_admin_peers_html
// Render the admin global Peers page: a paged, swarm-wide table of peers
// (client, torrent, country, address, state, transfer totals, last seen) with a
// server-side filter. The Country column appears only when stats_geo is on —
// the code is resolved from the peer's IP by the controller for this render and
// never written back, as with the client label. The address itself is stored;
// this page is the swarm index, and listing it is the point.
//
// The torrent column shows the registry name, or the
// (truncated) info_hash for an unregistered swarm. The header reports the
// swarm-wide totals and the current page window, with prev/next paging. The
// per-torrent drill-down (?page=peers&info_hash=…) is a separate view. Marks
// the Peers nav active. Wrapped in the shared admin layout. Returns HTML string.
//
// Parameters:
//   $settings - settings array
//   $peers - this page's peer rows (peers_select_all() shape + the transient
//            'client' label and 'country'/'country_name' codes)
//   $total - total active peers across all swarms
//   $swarms - distinct swarm count
//   $offset - the page's starting offset into the full listing
//   $limit - rows per page
//   $csrf_token - per-session token for the layout's logout form

/**
 * @param PhoenixSettings $settings
 * @param list<array{
 *     info_hash: string,
 *     peer_id: string,
 *     ipv4: string,
 *     ipv6: string,
 *     portv4: int,
 *     portv6: int,
 *     uploaded: int,
 *     downloaded: int,
 *     left: int,
 *     state: int,
 *     updated: int,
 *     name: string|null,
 *     filename: string|null,
 *     client: string,
 *     country?: string,
 *     country_name?: string,
 * }> $peers
 */
function view_admin_peers_html(
    array $settings,
    array $peers,
    int $total,
    int $swarms,
    int $offset,
    int $limit,
    string $csrf_token,
    string $search = '',
    int $state = -1,
    string $sort = 'updated',
    string $dir = 'desc',
    string $info_hash = '',
    ?string $torrent_name = null,
): string {
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/../functions/format.bytes.php';

    // Every link on the page has to carry the current query state, or paging
    // would silently drop the filter and sorting would reset it.
    $query = static function (array $overrides) use ($search, $state, $sort, $dir, $info_hash): string {
        $params = ['page' => 'peers'];
        if ($info_hash !== '') {
            $params['info_hash'] = $info_hash;
        }
        if ($search !== '') {
            $params['q'] = $search;
        }
        if ($state === 0 || $state === 1) {
            $params['state'] = (string) $state;
        }
        if ($sort !== 'updated' || $dir !== 'desc') {
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

    // The column is shown whenever geo is switched on. If it is on but the
    // library or database is missing, every row reads as a dash — which is a
    // truer signal than hiding the column would be.
    $show_geo = $settings['stats_geo'] === true;

    $actions = '<span class="ph-count"><b>'.number_format($total).'</b> active peers &middot; '.number_format($swarms).' swarm'.($swarms === 1 ? '' : 's').'</span>';

    $rows = '';
    foreach ($peers as $peer) {
        $client = '<span class="badge">'.htmlspecialchars($peer['client'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</span>';

        // Registry name, or the truncated hash for an unregistered swarm.
        // The column has room for the display name alone, but that is not an
        // identifier: the same release rebuilt carries the same name under a
        // different hash. Both, plus the filename, go in the tooltip.
        $torrent_title = $peer['info_hash'];
        if ($peer['filename'] !== null && $peer['filename'] !== '') {
            $torrent_title .= "\n".$peer['filename'];
        }
        $torrent_title = htmlspecialchars($torrent_title, ENT_QUOTES, 'UTF-8');

        if ($peer['name'] !== null && $peer['name'] !== '') {
            $torrent = '<abbr class="muted nowrap ph-plain" title="'.$torrent_title.'">'.htmlspecialchars($peer['name'], ENT_QUOTES, 'UTF-8').'</abbr>';
        } else {
            $torrent = '<abbr class="mono dim ph-plain" title="'.$torrent_title.'">'.htmlspecialchars(substr($peer['info_hash'], 0, 12), ENT_QUOTES, 'UTF-8').'&hellip;</abbr>';
        }

        $addrs = [];
        if ($peer['ipv4'] !== '') {
            $addrs[] = [
                'ip' => $peer['ipv4'],
                'full' => htmlspecialchars($peer['ipv4'].':'.$peer['portv4'], ENT_QUOTES, 'UTF-8'),
            ];
        }
        if ($peer['ipv6'] !== '') {
            $addrs[] = [
                'ip' => $peer['ipv6'],
                'full' => htmlspecialchars('['.$peer['ipv6'].']:'.$peer['portv6'], ENT_QUOTES, 'UTF-8'),
            ];
        }
        // One row per address rather than <br>-joined, so each truncates on its
        // own and carries its own copy button — the displayed form is elided, so
        // copying is the only way to get the whole value back out. The entries
        // are already escaped, so they are safe in the attributes too.
        // The address links to every row sharing it — which, since a peer holds
        // one row per torrent, is that peer's torrents, with the window count
        // reporting how many. The IP alone, not the port: a client keeps its
        // listening port across swarms, but matching on the address is what
        // survives a client that does not.
        $address = $addrs === []
            ? '<span class="dim">&mdash;</span>'
            : implode('', array_map(
                static function (array $addr) use ($query): string {
                    return '<span class="ph-addr-row">'.
                        '<a class="ph-addr" href="'.$query(['q' => $addr['ip'], 'info_hash' => null, 'offset' => null]).
                        '" title="Show this peer&rsquo;s torrents &mdash; '.$addr['full'].'">'.$addr['full'].'</a>'.
                        '<button class="ph-copy" type="button" title="Copy address" aria-label="Copy address" data-copy="'.$addr['full'].'">'.
                        '<span class="ph-ico" data-lucide="copy"></span>'.
                        '</button>'.
                        '</span>';
                },
                $addrs,
            ));

        // ISO code, full name on hover. Deliberately not a flag: Phoenix ships
        // no flag artwork, and emoji flags render as bare letters on Windows,
        // which is the worst of both. Built only when the column is shown, so
        // the geo keys are required only when geo is actually on.
        $country = '';
        if ($show_geo) {
            $country = ($peer['country'] ?? '') === ''
                ? '<span class="dim">&mdash;</span>'
                : '<abbr class="ph-cc" title="'.htmlspecialchars((string) ($peer['country_name'] ?? ''), ENT_QUOTES, 'UTF-8').'">'.
                    htmlspecialchars((string) $peer['country'], ENT_QUOTES, 'UTF-8').'</abbr>';
        }

        // Not $state — that is the filter parameter, and the loop would clobber it.
        $state_cell = $peer['state'] === 1
            ? '<span class="listed">Seeding</span>'
            : '<span class="listed is-leeching">Leeching</span>';

        $rows .= '<tr>'.
            '<td><span class="flex items-center gap-2">'.$client.'</span></td>'.
            ($info_hash === '' ? '<td>'.$torrent.'</td>' : '').
            ($show_geo ? '<td>'.$country.'</td>' : '').
            '<td class="mono">'.$address.'</td>'.
            '<td>'.$state_cell.'</td>'.
            '<td class="table-col-numeric mono">'.format_bytes($peer['uploaded']).'</td>'.
            '<td class="table-col-numeric mono">'.format_bytes($peer['downloaded']).'</td>'.
            '<td class="table-col-numeric mono">'.format_bytes($peer['left']).'</td>'.
            '<td class="mono muted">'.date('Y-m-d H:i', $peer['updated']).'</td>'.
            '</tr>';
    }

    // Page window summary + prev/next paging.
    $shown = count($peers);
    $first = $shown === 0 ? 0 : $offset + 1;
    $last = $offset + $shown;
    $window = $shown === 0
        ? 'Showing 0 of '.number_format($total)
        : 'Showing '.number_format($first).'&ndash;'.number_format($last).' of '.number_format($total);

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

    // A GET form, not a client-side filter: the table is paged, so filtering in
    // the browser would only ever search the rendered page.
    $state_options = '';
    foreach ([-1 => 'All peers', 1 => 'Seeding', 0 => 'Leeching'] as $value => $label) {
        $state_options .= '<option value="'.($value === -1 ? '' : $value).'"'.
            ($state === $value ? ' selected' : '').'>'.$label.'</option>';
    }

    $sort_state = '';
    if ($sort !== 'updated' || $dir !== 'desc') {
        $sort_state = '<input type="hidden" name="sort" value="'.htmlspecialchars($sort, ENT_QUOTES, 'UTF-8').'">'.
            '<input type="hidden" name="dir" value="'.htmlspecialchars($dir, ENT_QUOTES, 'UTF-8').'">';
    }

    // When filtered to one swarm, say which — and offer the way back out. The
    // Torrent column is dropped: it would repeat the same name on every row.
    $swarm_banner = '';
    if ($info_hash !== '') {
        $swarm_banner = '<div class="alert alert-info"><span class="ph-ico" data-lucide="filter"></span><div>'.
            'Peers for <b>'.($torrent_name !== null && $torrent_name !== ''
                ? htmlspecialchars($torrent_name, ENT_QUOTES, 'UTF-8')
                : '<span class="mono">'.htmlspecialchars(substr($info_hash, 0, 12), ENT_QUOTES, 'UTF-8').'&hellip;</span>').'</b> '.
            '<a href="?page=peers">Show all peers</a></div></div>';
    }

    $body = $swarm_banner.'<form method="GET" action="" class="ph-toolbar">
			<input type="hidden" name="page" value="peers">
			'.($info_hash !== '' ? '<input type="hidden" name="info_hash" value="'.htmlspecialchars($info_hash, ENT_QUOTES, 'UTF-8').'">' : '').'
			'.$sort_state.'
			<span class="ph-search"><span class="ph-ico" data-lucide="search"></span><input type="search" name="q" value="'.htmlspecialchars($search, ENT_QUOTES, 'UTF-8').'" aria-label="Search peers" placeholder="Search address, torrent, hash&hellip;"></span>
			<select name="state" aria-label="Filter by state" class="ph-select">'.$state_options.'</select>
			<button class="btn btn-secondary btn-sm" type="submit">Search</button>'.
            ($search !== '' || $state !== -1
                ? '<a class="btn btn-ghost btn-sm" href="'.$query(['q' => null, 'state' => null, 'offset' => null]).'">Clear</a>'
                : '').'
			<span class="ph-spacer"></span>
			<span class="dim text-sm">'.$window.'</span>
		</form>

		<div class="ph-card-table wide ph-nowrap">
			<table id="tbl-peers">
				<thead><tr>'.
                    '<th>Client</th>'.
                    ($info_hash === '' ? '<th>'.$sort_link('torrent', 'Torrent').'</th>' : '').
                    ($show_geo ? '<th>Country</th>' : '').
                    '<th>'.$sort_link('address', 'Address').'</th>'.
                    '<th>'.$sort_link('state', 'State').'</th>'.
                    '<th class="table-col-numeric">'.$sort_link('uploaded', 'Up').'</th>'.
                    '<th class="table-col-numeric">'.$sort_link('downloaded', 'Down').'</th>'.
                    '<th class="table-col-numeric">'.$sort_link('left', 'Left').'</th>'.
                    '<th>'.$sort_link('updated', 'Last seen').'</th>'.
                '</tr></thead>
				<tbody>'.$rows.'</tbody>
			</table>
			<div class="ph-empty"'.($peers === [] ? '' : ' hidden').'>
				<span class="ph-ico" data-lucide="users"></span>
				<p>No active peers.</p>
			</div>
		</div>
		'.$pager;

    return view_admin_layout_html($settings, 'Peers', $body, 'peers', $csrf_token, 'Tracker', $actions, 'wide', '', '', ['/assets/copy.js']);
}
