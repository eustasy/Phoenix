<?php

declare(strict_types=1);

////	view_admin_torrents_html
// Render the admin Torrents management page: a filterable/sortable table of
// every torrent with swarm stats, and per-row List/Unlist + Edit + Peers +
// Delete controls (the toggle and delete are CSRF-protected POSTs carrying the
// info_hash). Untrusted strings (name, owner) are htmlspecialchars()-escaped;
// info_hash is validated 40-char hex. A second table lists unregistered swarms
// (peers with no torrents row). Wrapped in the shared admin layout. Returns
// HTML string.
//
// Input is the normalised torrents_select_all() shape; the caller supplies the
// CSRF token and an optional action message.

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
function view_admin_torrents_html(array $settings, array $torrents, string|false $message, string $csrf_token, array $swarms = []): string
{
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/html.hash.php';
    require_once __DIR__.'/../functions/format.bytes.php';

    // Hidden field carrying the CSRF token, embedded in every action form.
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';
    $sort_ico = '<span class="ph-sort-ico"><span class="ph-sort-asc ph-ico" data-lucide="chevron-up"></span><span class="ph-sort-desc ph-ico" data-lucide="chevron-down"></span></span>';

    $body = '';

    if ($message) {
        $body .= '<div class="alert alert-info" style="display:flex;gap:var(--space-2);align-items:flex-start"><span class="ph-ico" data-lucide="info" style="flex-shrink:0"></span><div>'.htmlspecialchars($message).'</div></div>';
    }

    if ($torrents === []) {
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
            $toggle_form = '<form method="POST" style="display:inline">'.
                '<input type="hidden" name="process" value="torrent_listed">'.
                '<input type="hidden" name="info_hash" value="'.$info_hash.'">'.
                '<input type="hidden" name="listed" value="'.($listed === 1 ? 0 : 1).'">'.$csrf_field.
                '<button type="submit" class="btn btn-ghost btn-xs">'.($listed === 1 ? 'Unlist' : 'List').'</button></form>';

            $delete_form = '<form method="POST" style="display:inline" onsubmit="return confirm(\'Delete this torrent and its peers?\')">'.
                '<input type="hidden" name="process" value="torrent_delete">'.
                '<input type="hidden" name="info_hash" value="'.$info_hash.'">'.$csrf_field.
                '<button type="submit" class="btn btn-ghost btn-xs" style="color:var(--color-danger)">Delete</button></form>';

            $edit_link = '<a class="btn btn-ghost btn-xs" href="?page=edit&amp;info_hash='.$info_hash.'">Edit</a>';
            $peers_link = '<a class="btn btn-ghost btn-xs" href="?page=peers&amp;info_hash='.$info_hash.'">Peers</a>';

            $listed_cell = $listed === 1
                ? '<span class="listed">Listed</span>'
                : '<span class="listed is-no">Unlisted</span>';

            $rows .= '<tr>'.
                '<td><span class="ph-name">'.$name.'</span></td>'.
                '<td>'.$owner.'</td>'.
                '<td>'.view_hash_html($info_hash).'</td>'.
                '<td class="table-col-numeric mono" data-sort="'.$torrent['size'].'">'.format_bytes($torrent['size']).'</td>'.
                '<td class="table-col-numeric">'.number_format($torrent['seeders']).'</td>'.
                '<td class="table-col-numeric">'.number_format($torrent['leechers']).'</td>'.
                '<td class="table-col-numeric">'.number_format($torrent['downloads']).'</td>'.
                '<td>'.$listed_cell.'</td>'.
                '<td><div class="row-actions">'.$toggle_form.$edit_link.$peers_link.$delete_form.'</div></td>'.
                '</tr>';
        }

        $count = count($torrents);
        $body .= '<div class="ph-toolbar">
			<span class="ph-search"><span class="ph-ico" data-lucide="search"></span><input type="search" aria-label="Search torrents" placeholder="Search name, owner, hash&hellip;" oninput="phFilterTable(this, \'#tbl-torrents\', \'#torrents-count\')"></span>
			<span class="ph-spacer"></span>
			<span class="ph-count" id="torrents-count">'.$count.' '.($count === 1 ? 'torrent' : 'torrents').'</span>
		</div>
		<div class="ph-card-table wide">
			<table id="tbl-torrents">
				<thead><tr>'.
                    '<th class="ph-sort" data-type="text">Name '.$sort_ico.'</th>'.
                    '<th class="ph-sort" data-type="text">Owner</th>'.
                    '<th>Info hash</th>'.
                    '<th class="ph-sort table-col-numeric" data-type="num">Size</th>'.
                    '<th class="ph-sort table-col-numeric" data-type="num">Seed</th>'.
                    '<th class="ph-sort table-col-numeric" data-type="num">Leech</th>'.
                    '<th class="ph-sort table-col-numeric" data-type="num">DL</th>'.
                    '<th>Listed</th>'.
                    '<th class="tar">Actions</th>'.
                '</tr></thead><tbody>'.$rows.'</tbody></table>
		</div>';
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

        $body .= '<div class="ph-section-head"><h3>Unregistered swarms</h3><span class="dim" style="font-size:var(--font-size-sm)">Active peers, no torrents row</span></div>'.
            '<div class="ph-card-table"><table>'.
            '<thead><tr><th>Info hash</th><th class="table-col-numeric">Seed</th><th class="table-col-numeric">Leech</th><th class="table-col-numeric">Peers</th><th class="tar">Actions</th></tr></thead>'.
            '<tbody>'.$swarm_rows.'</tbody></table></div>';
    }

    $actions = '<a class="btn btn-primary btn-sm" href="?page=add"><span class="ph-ico" data-lucide="plus"></span>Add Torrent</a>';

    return view_admin_layout_html($settings, 'Torrents', $body, 'torrents', $csrf_token, 'Tracker', $actions, false, '', 'phMakeSortable(\'#tbl-torrents\');');
}
