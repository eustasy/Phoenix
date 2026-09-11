<?php

declare(strict_types=1);

////	view_admin_torrents_html
// Render the admin Torrents management page: one page of a searchable, sortable
// listing with swarm stats, and per-row List/Unlist + Edit + Peers + Delete
// controls (the toggle and delete are CSRF-protected POSTs carrying the
// info_hash). Untrusted strings (name, owner) are htmlspecialchars()-escaped;
// info_hash is validated 40-char hex. A second table lists unregistered swarms
// (peers with no torrents row). Wrapped in the shared admin layout. Returns
// HTML string.
//
// Input is the normalised torrents_select_all() shape, already filtered, sorted
// and cut to one page; the caller supplies the CSRF token, an optional action
// message, and the filter state so the toolbar, the sort headers and the pager
// links can carry it forward.
//
// $total is the number of torrents the filter matched, not the number rendered
// — the pager needs to know there is a next page, and the header count should
// not read as though the page were the whole tracker.

/**
 * @param PhoenixSettings $settings
 * @param list<array{
 *     info_hash: string|null,
 *     user: string|null,
 *     name: string|null,
 *     size: int,
 *     listed: int,
 *     downloads: int,
 *     seeders: int,
 *     leechers: int,
 *     peers: int,
 *     traffic: int,
 *     filename: string|null,
 *     files: list<array{path: string, length: int}>|null,
 *     trackers: list<string>|null,
 *     webseeds: list<string>|null,
 * }> $torrents
 * @param list<array{info_hash: string, seeders: int, leechers: int, peers: int}> $swarms
 */
function view_admin_torrents_html(array $settings, array $torrents, string|false $message, string $csrf_token, array $swarms = [], int $total = 0, int $offset = 0, int $limit = 100, string $search = '', int $listed_filter = -1, string $sort = 'seeders', string $dir = 'desc'): string
{
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/html.hash.php';
    require_once __DIR__.'/../functions/format.bytes.php';

    // Hidden field carrying the CSRF token, embedded in every action form.
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    // Every link out of this page carries the current filter, so sorting a
    // search does not silently drop back to the whole table. Defaults are left
    // out of the query string to keep shareable URLs short.
    $query = static function (array $overrides) use ($search, $listed_filter, $sort, $dir, $offset): string {
        $params = ['page' => 'torrents', 'offset' => $offset > 0 ? (string) $offset : null];
        if ($search !== '') {
            $params['q'] = $search;
        }
        if ($listed_filter === 0 || $listed_filter === 1) {
            $params['listed'] = (string) $listed_filter;
        }
        if ($sort !== 'seeders' || $dir !== 'desc') {
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

    $body = '';

    if ($message) {
        $body .= '<div class="alert alert-info"><span class="ph-ico" data-lucide="info"></span><div>'.htmlspecialchars($message).'</div></div>';
    }

    // An empty page under a filter is a different fact from an empty tracker,
    // and the reader needs the way back out rather than being told there is
    // nothing here.
    if ($torrents === [] && ($search !== '' || $listed_filter !== -1)) {
        $body .= '<div class="ph-empty"><span class="ph-ico" data-lucide="search-x"></span><p>No torrents match this filter. '.
            '<a href="?page=torrents">Show all torrents</a></p></div>';
    } elseif ($torrents === []) {
        $body .= '<div class="ph-empty"><span class="ph-ico" data-lucide="database"></span><p>No torrents are registered.</p></div>';
    } else {
        $rows = '';
        foreach ($torrents as $torrent) {
            $info_hash = (string) $torrent['info_hash'];
            $name = htmlspecialchars((string) ($torrent['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $owner = $torrent['user'] === null
                ? '<span class="muted">&mdash;</span>'
                : '<span class="badge badge-cyan">'.htmlspecialchars($torrent['user'], ENT_QUOTES, 'UTF-8').'</span>';
            $listed = $torrent['listed'];

            // Toggle form sends the OPPOSITE of the current listed state. List/
            // Unlist sits first so the trailing actions stay aligned across rows.
            $toggle_form = '<form method="POST" class="d-inline">'.
                '<input type="hidden" name="process" value="torrent_listed">'.
                '<input type="hidden" name="info_hash" value="'.$info_hash.'">'.
                '<input type="hidden" name="listed" value="'.($listed === 1 ? 0 : 1).'">'.$csrf_field.
                '<button type="submit" class="btn btn-ghost btn-xs">'.($listed === 1 ? 'Unlist' : 'List').'</button></form>';

            $delete_form = '<form method="POST" class="d-inline" data-confirm="Delete this torrent and its peers?">'.
                '<input type="hidden" name="process" value="torrent_delete">'.
                '<input type="hidden" name="info_hash" value="'.$info_hash.'">'.$csrf_field.
                '<button type="submit" class="btn btn-ghost btn-xs is-danger">Delete</button></form>';

            $edit_link = '<a class="btn btn-ghost btn-xs" href="?page=edit&amp;info_hash='.$info_hash.'">Edit</a>';
            $peers_link = '<a class="btn btn-ghost btn-xs" href="?page=peers&amp;info_hash='.$info_hash.'">Peers</a>';

            $listed_cell = $listed === 1
                ? '<span class="listed">Listed</span>'
                : '<span class="listed is-no">Unlisted</span>';

            // The meta fields the table does not render — filename, file paths,
            // trackers, webseeds — used to ride along in data-search so the
            // browser-side filter could match them. They are matched in SQL now
            // (torrents_filter_sql), which searches every torrent rather than
            // the rendered page, so the attribute would only be dead weight.
            $rows .= '<tr>'.
                '<td><span class="ph-name">'.$name.'</span></td>'.
                '<td>'.$owner.'</td>'.
                '<td>'.view_hash_html($info_hash).'</td>'.
                '<td class="table-col-numeric mono">'.format_bytes($torrent['size']).'</td>'.
                '<td class="table-col-numeric">'.number_format($torrent['seeders']).'</td>'.
                '<td class="table-col-numeric">'.number_format($torrent['leechers']).'</td>'.
                '<td class="table-col-numeric">'.number_format($torrent['downloads']).'</td>'.
                '<td>'.$listed_cell.'</td>'.
                '<td><div class="row-actions">'.$toggle_form.$edit_link.$peers_link.$delete_form.'</div></td>'.
                '</tr>';
        }

        // Page window summary + prev/next paging. This branch only runs with
        // rows in hand, so there is always a window to describe.
        $last = $offset + count($torrents);
        $window = 'Showing '.number_format($offset + 1).'&ndash;'.number_format($last).' of '.number_format($total);

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

        // A GET form, not a client-side filter: the table is paged, so filtering
        // in the browser would only ever search the rendered page.
        $listed_options = '';
        foreach ([-1 => 'All torrents', 1 => 'Listed', 0 => 'Unlisted'] as $value => $label) {
            $listed_options .= '<option value="'.($value === -1 ? '' : $value).'"'.
                ($listed_filter === $value ? ' selected' : '').'>'.$label.'</option>';
        }

        // Sorting survives a search because the form carries it, rather than
        // resetting to the default the moment the reader types anything.
        $sort_state = '';
        if ($sort !== 'seeders' || $dir !== 'desc') {
            $sort_state = '<input type="hidden" name="sort" value="'.htmlspecialchars($sort, ENT_QUOTES, 'UTF-8').'">'.
                '<input type="hidden" name="dir" value="'.htmlspecialchars($dir, ENT_QUOTES, 'UTF-8').'">';
        }

        $body .= '<form method="GET" action="" class="ph-toolbar">
			<input type="hidden" name="page" value="torrents">
			'.$sort_state.'
			<span class="ph-search"><span class="ph-ico" data-lucide="search"></span><input type="search" name="q" value="'.htmlspecialchars($search, ENT_QUOTES, 'UTF-8').'" aria-label="Search torrents" placeholder="Search name, owner, hash, file, tracker&hellip;"></span>
			<select name="listed" aria-label="Filter by listing" class="ph-select">'.$listed_options.'</select>
			<button class="btn btn-sm" type="submit">Search</button>'.
            ($search !== '' || $listed_filter !== -1
                ? '<a class="btn btn-ghost btn-sm" href="'.$query(['q' => null, 'listed' => null, 'offset' => null]).'">Clear</a>'
                : '').'
			<span class="ph-spacer"></span>
			<span class="dim text-sm">'.$window.'</span>
		</form>

		<div class="ph-card-table wide">
			<table id="tbl-torrents">
				<thead><tr>'.
                    '<th>'.$sort_link('name', 'Name').'</th>'.
                    '<th>'.$sort_link('user', 'Owner').'</th>'.
                    '<th>Info hash</th>'.
                    '<th class="table-col-numeric">'.$sort_link('size', 'Size').'</th>'.
                    '<th class="table-col-numeric">'.$sort_link('seeders', 'Seed').'</th>'.
                    '<th class="table-col-numeric">'.$sort_link('leechers', 'Leech').'</th>'.
                    '<th class="table-col-numeric">'.$sort_link('downloads', 'DL').'</th>'.
                    '<th>'.$sort_link('listed', 'Listed').'</th>'.
                    '<th class="tar">Actions</th>'.
                '</tr></thead><tbody>'.$rows.'</tbody></table>
		</div>'.$pager;
    }

    ////	Unregistered swarms
    // Hashes with active peers but no torrents row (open-tracker swarms that
    // were never registered). Each links to its peer drill-down.
    if ($swarms !== []) {
        $swarm_rows = '';
        foreach ($swarms as $swarm) {
            $hash = (string) $swarm['info_hash'];
            $swarm_rows .= '<tr>'.
                '<td>'.view_hash_html($hash).'</td>'.
                '<td class="table-col-numeric">'.number_format($swarm['seeders']).'</td>'.
                '<td class="table-col-numeric">'.number_format($swarm['leechers']).'</td>'.
                '<td class="table-col-numeric">'.number_format($swarm['peers']).'</td>'.
                '<td><div class="row-actions"><a class="btn btn-ghost btn-xs" href="?page=peers&amp;info_hash='.htmlspecialchars($hash, ENT_QUOTES, 'UTF-8').'">Peers</a></div></td>'.
                '</tr>';
        }

        $body .= '<div class="ph-section-head"><h3>Unregistered swarms</h3><span class="dim text-sm">Active peers, no torrents row</span></div>'.
            '<div class="ph-card-table"><table>'.
            '<thead><tr><th>Info hash</th><th class="table-col-numeric">Seed</th><th class="table-col-numeric">Leech</th><th class="table-col-numeric">Peers</th><th class="tar">Actions</th></tr></thead>'.
            '<tbody>'.$swarm_rows.'</tbody></table></div>';
    }

    $actions = '<span class="ph-count"><b>'.number_format($total).'</b> torrent'.($total === 1 ? '' : 's').'</span>'.
        '<a class="btn btn-primary btn-sm" href="?page=add"><span class="ph-ico" data-lucide="plus"></span>Add Torrent</a>';

    // tables.js is gone with the client-side filter and sort it drove; nine
    // columns of actions want the wide body, as the Peers listing does.
    return view_admin_layout_html($settings, 'Torrents', $body, 'torrents', $csrf_token, 'Tracker', $actions, 'wide', '', '', ['/assets/copy.js']);
}
