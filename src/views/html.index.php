<?php

declare(strict_types=1);

////	view_index_html
// Renders a normalized $index array as the public Torrent Index: a dense,
// client-filterable/sortable table of explicitly-listed torrents wrapped in the
// public page chrome. Columns: Title, File*, Hash, Trackers*, Webseeds*,
// Seeders, Leechers, Downloads, Health, Magnet — the starred meta columns
// appear only when $show_meta is set (mirroring the JSON/XML views'
// index_show_meta gating); the magnet link (built by public/index.php) renders
// either way. Health is the seeder share of the swarm; an empty swarm shows a
// dash. Filtering and sorting are progressive enhancements (assets/app.js) — the
// table is complete and readable without JavaScript.
// Returns HTML string. Caller is responsible for setting Content-Type header.

/** @param list<array{info_hash: string|null, name: string|null, size: int, downloads: int, seeders: int, leechers: int, peers: int, traffic: int, filename?: string|null, files?: list<array{path: string, length: int}>|null, trackers?: list<string>|null, webseeds?: list<string>|null, magnet?: string|null}> $index */
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
    $head = '<th class="ph-sort" data-type="text">Title '.$sort_ico.'</th>';
    if ($show_meta) {
        $head .= '<th>File</th>';
    }
    $head .= '<th>Hash</th>';
    if ($show_meta) {
        $head .= '<th>Trackers</th><th>Webseeds</th>';
    }
    $head .= '<th class="ph-sort table-col-numeric" data-type="num">Seeders '.$sort_ico.'</th>'.
        '<th class="ph-sort table-col-numeric" data-type="num">Leechers</th>'.
        '<th class="ph-sort table-col-numeric" data-type="num">Downloads</th>'.
        '<th class="ph-sort" data-type="num" style="width:150px">Health</th>'.
        '<th class="tar">Magnet</th>';

    ////	Body rows
    $rows = '';
    foreach ($index as $torrent) {
        $swarm = $torrent['seeders'] + $torrent['leechers'];
        $health_sort = $swarm === 0 ? -1 : (int) round($torrent['seeders'] / $swarm * 100);

        $cells = '<td><span class="ph-name">'.htmlspecialchars($torrent['name'] ?? '').'</span></td>';
        if ($show_meta) {
            $cells .= '<td>'.(htmlspecialchars($torrent['filename'] ?? '') ?: '&mdash;').'</td>';
        }
        $cells .= '<td>'.view_hash_html($torrent['info_hash'] ?? '').'</td>';
        if ($show_meta) {
            $cells .= '<td>'.$url_list($torrent['trackers'] ?? null).'</td>';
            $cells .= '<td>'.$url_list($torrent['webseeds'] ?? null).'</td>';
        }
        $cells .= '<td class="table-col-numeric">'.number_format($torrent['seeders']).'</td>';
        $cells .= '<td class="table-col-numeric">'.number_format($torrent['leechers']).'</td>';
        $cells .= '<td class="table-col-numeric">'.number_format($torrent['downloads']).'</td>';
        $cells .= '<td data-sort="'.$health_sort.'">'.view_health_html($torrent['seeders'], $torrent['leechers']).'</td>';

        // Magnet URIs join parameters with '&', so the href must be escaped.
        $magnet = $torrent['magnet'] ?? null;
        $cells .= '<td class="tar">'.($magnet !== null
            ? '<a class="magnet-link" href="'.htmlspecialchars($magnet).'"><span class="ph-ico" data-lucide="magnet"></span>magnet</a>'
            : '&mdash;').'</td>';

        $rows .= '<tr>'.$cells.'</tr>';
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
			<input type="search" aria-label="Filter torrents" placeholder="Filter by title or hash&hellip;" oninput="phFilterTable(this, \'#tbl-index\', \'#idx-count\')">
		</span>
		<span class="ph-spacer"></span>
		<span class="ph-count"><b id="idx-count">'.$count_label.'</b></span>
	</div>

	<div class="ph-card-table wide">
		<table id="tbl-index">
			<thead><tr>'.$head.'</tr></thead>
			<tbody>'.$rows.'</tbody>
		</table>
		<div class="ph-empty" hidden>
			<span class="ph-ico" data-lucide="search-x"></span>
			<p>No torrents match your filter.</p>
		</div>
	</div>

	<p class="dim" style="font-size:var(--font-size-sm);margin-top:var(--space-4)">Health is the seeder share of each swarm. Click a hash to copy it.</p>';

    return view_public_layout_html('Torrent Index — Phoenix', $body, 'index', $version, false, '', 'phMakeSortable(\'#tbl-index\');');
}
