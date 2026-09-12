<?php

declare(strict_types=1);

////	view_index_html
// Renders a normalized $index array as the public Torrent Index: a dense,
// client-filterable/sortable table of explicitly-listed torrents wrapped in the
// public page chrome. Columns: Title, Hash, Seeders, Leechers, Downloads,
// Health, Magnet. When $show_meta is set (mirroring the JSON/XML views'
// index_show_meta gating) each torrent gains a second, full-width row carrying
// its filename, file count, trackers and webseeds — meta is far wider than the
// figures beside it, so it gets a row rather than columns. Each torrent is its
// own <tbody> so the pair sorts and filters as one unit. The magnet link (built
// by public/index.php) renders either way. Health is the seeder share of the
// swarm; an empty swarm shows a dash. Filtering and sorting are progressive
// enhancements (assets/tables.js) — the table is complete without JavaScript.
// Returns HTML string. Caller is responsible for setting Content-Type header.

/** @param list<array{info_hash: string|null, name: string|null, size: int, downloads: int, seeders: int, leechers: int, peers: int, bandwidth: int, filename?: string|null, files?: list<array{path: string, length: int}>|null, trackers?: list<string>|null, webseeds?: list<string>|null, magnet?: string|null}> $index */
function view_index_html(array $index, bool $show_meta = false, string $version = ''): string
{
    require_once __DIR__.'/html.public.layout.php';
    require_once __DIR__.'/html.health.php';
    require_once __DIR__.'/html.hash.php';

    // One URL per line inside a cell; a dash when the torrent carries none.
    /** @param list<string>|null $urls */
    $url_list = static function (?array $urls): string {
        if (empty($urls)) {
            return '&mdash;';
        }

        return implode('<br>', array_map(
            static fn (string $url): string => htmlspecialchars($url),
            $urls,
        ));
    };

    // Sort indicator markup reused by each sortable header.
    $sort_ico = '<span class="ph-sort-ico"><span class="ph-sort-asc ph-ico" data-lucide="chevron-up"></span><span class="ph-sort-desc ph-ico" data-lucide="chevron-down"></span></span>';

    ////	Header row
    // Meta gets no columns of its own — filenames and URL lists are far wider
    // than the figures beside them, and giving each a column squeezed the rest
    // of the table to nothing. It goes on a second row per torrent instead, so
    // the column count is the same either way.
    $head = '<th class="ph-sort" data-type="text">Title '.$sort_ico.'</th>'.
        '<th>Hash</th>'.
        '<th class="ph-sort table-col-numeric" data-type="num" data-sort-default="desc">Seeders '.$sort_ico.'</th>'.
        '<th class="ph-sort table-col-numeric" data-type="num">Leechers</th>'.
        '<th class="ph-sort table-col-numeric" data-type="num">Downloads</th>'.
        '<th class="ph-sort col-health" data-type="num">Health '.$sort_ico.'</th>'.
        '<th class="tar">Magnet</th>';

    ////	Body rows
    $rows = '';
    foreach ($index as $position => $torrent) {
        $swarm = $torrent['seeders'] + $torrent['leechers'];
        $health_sort = $swarm === 0 ? -1 : (int) round($torrent['seeders'] / $swarm * 100);

        // The title doubles as the disclosure control for the meta row, so the
        // cells are built after the meta row is known — a torrent with nothing
        // to disclose gets a plain title rather than a control that opens an
        // empty drawer. $title_cell is filled in below.
        $cells = '';
        $cells .= '<td>'.view_hash_html($torrent['info_hash'] ?? '').'</td>';
        $cells .= '<td class="table-col-numeric">'.number_format($torrent['seeders']).'</td>';
        $cells .= '<td class="table-col-numeric">'.number_format($torrent['leechers']).'</td>';
        $cells .= '<td class="table-col-numeric">'.number_format($torrent['downloads']).'</td>';
        $cells .= '<td data-sort="'.$health_sort.'">'.view_health_html($torrent['seeders'], $torrent['leechers']).'</td>';

        // Magnet URIs join parameters with '&', so the href must be escaped.
        $magnet = $torrent['magnet'] ?? null;
        $cells .= '<td class="tar">'.($magnet !== null
            ? '<a class="magnet-link" href="'.htmlspecialchars($magnet).'"><span class="ph-ico" data-lucide="magnet"></span>magnet</a>'
            : '&mdash;').'</td>';

        ////	Meta row
        // A second row beneath the torrent, spanning the full width, carrying
        // the fields that have no column. Gated on $show_meta exactly as the
        // old columns were, so nothing the operator withholds is emitted.
        // Individual file paths stay out of the visible row — a large torrent
        // has hundreds — but ride along in data-search so they stay findable.
        $meta_row = '';
        $search_attr = '';
        if ($show_meta) {
            $parts = '';
            if (! empty($torrent['filename'])) {
                $parts .= '<span class="idx-meta-item"><span class="idx-meta-key">File</span>'.
                    '<span class="mono">'.htmlspecialchars($torrent['filename']).'</span></span>';
            }
            $files = $torrent['files'] ?? [];
            if ($files !== []) {
                $parts .= '<span class="idx-meta-item"><span class="idx-meta-key">Files</span>'.
                    '<span>'.number_format(count($files)).'</span></span>';
            }
            foreach (['trackers' => 'Trackers', 'webseeds' => 'Webseeds'] as $key => $label) {
                if (empty($torrent[$key])) {
                    continue;
                }
                $parts .= '<span class="idx-meta-item"><span class="idx-meta-key">'.$label.'</span>'.
                    '<span class="idx-meta-urls">'.$url_list($torrent[$key]).'</span></span>';
            }
            if ($parts !== '') {
                $meta_row = '<tr class="idx-meta"><td colspan="7">'.$parts.'</td></tr>';
            }

            $haystack = [];
            foreach ($files as $file) {
                $haystack[] = $file['path'];
            }
            if ($haystack !== []) {
                $search_attr = ' data-search="'.htmlspecialchars(implode(' ', $haystack), ENT_QUOTES, 'UTF-8').'"';
            }
        }

        // The title cell. With a meta row to show it is a disclosure control: a
        // checkbox (visually hidden, still focusable and still keyboard-
        // operable) whose label is the whole title, and CSS opens the drawer
        // off :checked. No JavaScript, so the control works on a page where
        // sorting and filtering do not.
        $name = '<span class="ph-name">'.htmlspecialchars($torrent['name'] ?? '').'</span>';
        if ($meta_row !== '') {
            $toggle = 'idx-t'.$position;
            $title_cell = '<td class="idx-title">'.
                '<input type="checkbox" class="idx-toggle ph-visually-hidden" id="'.$toggle.'">'.
                '<label class="idx-disclose" for="'.$toggle.'">'.
                '<span class="idx-chevron ph-ico" data-lucide="chevron-right"></span>'.$name.'</label></td>';
        } else {
            $title_cell = '<td class="idx-title">'.$name.'</td>';
        }

        // One <tbody> per torrent so tables.js treats the pair as a single unit
        // — sorting can never separate a torrent from its meta, and filtering
        // hides both together. It is also what lets the hover and the open
        // state cover both rows: they are one element to style.
        $rows .= '<tbody'.$search_attr.'><tr>'.$title_cell.$cells.'</tr>'.$meta_row.'</tbody>';
    }

    $count = count($index);
    $count_label = $count.' '.($count === 1 ? 'torrent' : 'torrents');

    $body = '<div class="ph-page-title">
		<div>
			<h1>Torrent Index</h1>
			<p>Explicitly-listed torrents tracked by this server.</p>
		</div>
	</div>

	<div class="ph-toolbar">
		<span class="ph-search">
			<span class="ph-ico" data-lucide="search"></span>
			<input type="search" aria-label="Filter torrents" placeholder="Filter by title, hash, file, tracker&hellip;" data-filter-table="#tbl-index" data-filter-count="#idx-count">
		</span>
		<span class="ph-spacer"></span>
		<span class="ph-count"><b id="idx-count">'.$count_label.'</b></span>
	</div>

	<div class="ph-card-table wide">
		<table id="tbl-index" class="idx-table">
			<thead><tr>'.$head.'</tr></thead>
			'.$rows.'
		</table>
		<div class="ph-empty" hidden>
			<span class="ph-ico" data-lucide="search-x"></span>
			<p>No torrents match your filter.</p>
		</div>
	</div>

	<p class="dim text-sm mt-4">Health is the seeder share of each swarm.</p>';

    $extra_head = '
	<link rel="stylesheet" href="/assets/index.css">';

    return view_public_layout_html('Torrent Index — Phoenix', $body, 'index', $version, false, $extra_head, '', ['/assets/copy.js', '/assets/tables.js']);
}
