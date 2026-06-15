<?php

declare(strict_types=1);

////	view_admin_peers_html
// Render the admin global Peers page: a swarm-wide table of peers (client,
// torrent, address, state, transfer totals, last seen) with a client-side
// filter. This is the UI only — it is NOT yet wired to the tracker; it renders
// preview/sample rows (clearly banner-flagged) so the operator can see the
// finished surface. A future change will list real peers across every swarm
// (with pagination). The per-torrent drill-down (?page=peers&info_hash=…) is a
// separate, live view. Marks the Peers nav active. Wrapped in the shared admin
// layout. Returns HTML string.

/** @param PhoenixSettings $settings */
function view_admin_peers_html(array $settings, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';

    $actions = '<span class="ph-count"><b>2,841</b> active peers &middot; 12 swarms</span>';

    // Preview/sample rows: client (+badge), torrent, address, state, transfers.
    $sample = [
        ['Transmission', 'badge-green', '4.1.1.0', 'Ubuntu 24.04.1 LTS', '81.78.207.83:51413', 1, '1.4 MB', '190 MB', '0', '11:02'],
        ['qBittorrent', 'badge-blue', '4.6.2.0', 'Ubuntu 24.04.1 LTS', '[2001:db8::1]:6881', 0, '0', '245 MB', '2.9 GB', '11:01'],
        ['µTorrent', 'badge-purple', '', 'Debian 12.7 netinst', '203.0.113.9:6881', 0, '12 KB', '98 MB', '1.0 GB', '10:58'],
        ['Deluge', 'badge-cyan', '2.1.1', 'Arch Linux 2026.06.01', '45.83.220.10:6881', 1, '880 KB', '0', '0', '10:57'],
        ['libtorrent', 'badge-orange', '', 'Fedora Workstation 41', '192.0.2.55:51413', 0, '4 KB', '33 MB', '1.4 GB', '10:56'],
        ['Unknown', '', '', 'Tails 6.4', '198.51.100.24:6889', 1, '3.2 MB', '0', '0', '10:55'],
    ];

    $rows = '';
    foreach ($sample as [$client, $badge, $version, $torrent, $address, $state, $up, $down, $left, $seen]) {
        $client_cell = '<span class="badge'.($badge !== '' ? ' '.$badge : '').'">'.$client.'</span>';
        if ($version !== '') {
            $client_cell .= '<span class="mono dim">'.$version.'</span>';
        }
        $state_cell = $state === 1
            ? '<span class="listed">Seeding</span>'
            : '<span class="listed is-no" style="color:var(--color-orange)">Leeching</span>';

        $rows .= '<tr>'.
            '<td><span class="flex items-center gap-2">'.$client_cell.'</span></td>'.
            '<td class="muted nowrap">'.$torrent.'</td>'.
            '<td class="mono">'.$address.'</td>'.
            '<td>'.$state_cell.'</td>'.
            '<td class="table-col-numeric mono">'.$up.'</td>'.
            '<td class="table-col-numeric mono">'.$down.'</td>'.
            '<td class="table-col-numeric mono">'.$left.'</td>'.
            '<td class="mono muted">'.$seen.'</td>'.
            '</tr>';
    }

    $body = '<div class="alert alert-info" style="display:flex;gap:var(--space-2);align-items:flex-start"><span class="ph-ico" data-lucide="info" style="flex-shrink:0"></span><div><strong>Preview &mdash; sample data.</strong> This swarm-wide listing is not wired to the tracker yet. To inspect a real swarm now, open a torrent\'s <em>Peers</em> drill-down from the <a href="?page=torrents">Torrents</a> page.</div></div>

		<div class="ph-toolbar">
			<span class="ph-search"><span class="ph-ico" data-lucide="search"></span><input type="search" aria-label="Search peers" placeholder="Search client, address, torrent&hellip;" oninput="phFilterTable(this, \'#tbl-peers\')"></span>
			<span class="ph-spacer"></span>
			<span class="dim" style="font-size:var(--font-size-sm)">Showing 6 of 2,841</span>
		</div>

		<div class="ph-card-table wide">
			<table id="tbl-peers">
				<thead><tr><th>Client</th><th>Torrent</th><th>Address</th><th>State</th><th class="table-col-numeric">Up</th><th class="table-col-numeric">Down</th><th class="table-col-numeric">Left</th><th>Last seen</th></tr></thead>
				<tbody>'.$rows.'</tbody>
			</table>
		</div>';

    return view_admin_layout_html($settings, 'Peers', $body, 'peers', $csrf_token, 'Tracker', $actions, false);
}
