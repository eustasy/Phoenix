<?php

declare(strict_types=1);

////	view_admin_torrent_peers_html
// Render the admin peer drill-down: one torrent's swarm as a table of client,
// address, state, transfer totals, and last-seen time. The subtitle shows the
// registry name when known, otherwise the raw info_hash (an unregistered
// swarm). All untrusted strings (client, IPs) are htmlspecialchars()-escaped.
// Marks the Torrents nav active since this is a drill-down beneath it. Wrapped
// in the shared admin layout. Returns HTML string.
//
/**
 * @param PhoenixSettings $settings
 * @param list<array{
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
 *     client: string,
 * }> $peers
 */
function view_admin_torrent_peers_html(array $settings, string $info_hash, ?string $name, array $peers, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/../functions/format.bytes.php';

    $subtitle = $name !== null && $name !== ''
        ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        : '<code>'.htmlspecialchars($info_hash, ENT_QUOTES, 'UTF-8').'</code>';

    $back = '<a class="btn btn-secondary btn-sm" href="?page=torrents"><span class="ph-ico" data-lucide="arrow-left"></span>Back</a>';

    $body = '<p class="muted mt-0">'.$subtitle.'</p>';

    if ($peers === []) {
        $body .= '<div class="ph-empty"><span class="ph-ico" data-lucide="users"></span><p>No active peers.</p></div>';
    } else {
        $rows = '';
        foreach ($peers as $peer) {
            // Show whichever address families the peer announced.
            $addrs = [];
            if ($peer['ipv4'] !== '') {
                $addrs[] = htmlspecialchars($peer['ipv4'].':'.$peer['portv4'], ENT_QUOTES, 'UTF-8');
            }
            if ($peer['ipv6'] !== '') {
                $addrs[] = htmlspecialchars('['.$peer['ipv6'].']:'.$peer['portv6'], ENT_QUOTES, 'UTF-8');
            }
            $address = $addrs === [] ? '<span class="dim">&mdash;</span>' : implode('<br>', $addrs);

            $state = $peer['state'] === 1
                ? '<span class="listed">Seeding</span>'
                : '<span class="listed is-leeching">Leeching</span>';

            $rows .= '<tr>'.
                '<td>'.htmlspecialchars($peer['client'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>'.
                '<td class="mono">'.$address.'</td>'.
                '<td>'.$state.'</td>'.
                '<td class="table-col-numeric mono" data-sort="'.$peer['uploaded'].'">'.format_bytes($peer['uploaded']).'</td>'.
                '<td class="table-col-numeric mono" data-sort="'.$peer['downloaded'].'">'.format_bytes($peer['downloaded']).'</td>'.
                '<td class="table-col-numeric mono" data-sort="'.$peer['left'].'">'.format_bytes($peer['left']).'</td>'.
                '<td class="mono muted" data-sort="'.$peer['updated'].'">'.date('Y-m-d H:i', $peer['updated']).'</td>'.
                '</tr>';
        }

        // Unlike the global Peers listing, this view is not paged — every peer
        // in the swarm is rendered — so the filter and sort can stay in the
        // browser and still see every row.
        $sort_ico = '<span class="ph-sort-ico"><span class="ph-sort-asc ph-ico" data-lucide="chevron-up"></span><span class="ph-sort-desc ph-ico" data-lucide="chevron-down"></span></span>';
        $count = count($peers);

        $body .= '<div class="ph-toolbar">
			<span class="ph-search"><span class="ph-ico" data-lucide="search"></span><input type="search" aria-label="Search peers" placeholder="Search client, address&hellip;" data-filter-table="#tbl-torrent-peers" data-filter-count="#torrent-peers-count"></span>
			<span class="ph-spacer"></span>
			<span class="ph-count" id="torrent-peers-count" data-noun="peer">'.$count.' '.($count === 1 ? 'peer' : 'peers').'</span>
		</div>
		<div class="ph-card-table wide"><table id="tbl-torrent-peers">'.
            '<thead><tr>'.
                '<th class="ph-sort" data-type="text">Client '.$sort_ico.'</th>'.
                '<th>Address</th>'.
                '<th class="ph-sort" data-type="text">State '.$sort_ico.'</th>'.
                '<th class="ph-sort table-col-numeric" data-type="num">Up '.$sort_ico.'</th>'.
                '<th class="ph-sort table-col-numeric" data-type="num">Down '.$sort_ico.'</th>'.
                '<th class="ph-sort table-col-numeric" data-type="num">Left '.$sort_ico.'</th>'.
                '<th class="ph-sort" data-type="num" data-sort-default="desc">Last seen '.$sort_ico.'</th>'.
            '</tr></thead>'.
            '<tbody>'.$rows.'</tbody></table></div>';
    }

    return view_admin_layout_html($settings, 'Peers', $body, 'torrents', $csrf_token, 'Tracker', $back, '', '', '', ['/assets/tables.js']);
}
