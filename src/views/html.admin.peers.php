<?php

declare(strict_types=1);

////	view_admin_peers_html
// Render the admin global Peers page: a paged, swarm-wide table of peers
// (client, torrent, address, state, transfer totals, last seen) with a
// client-side filter. The torrent column shows the registry name, or the
// (truncated) info_hash for an unregistered swarm. The header reports the
// swarm-wide totals and the current page window, with prev/next paging. The
// per-torrent drill-down (?page=peers&info_hash=…) is a separate view. Marks
// the Peers nav active. Wrapped in the shared admin layout. Returns HTML string.
//
// Parameters:
//   $settings - settings array
//   $peers - this page's peer rows (peers_select_all() shape + a 'client' label)
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
 *     client: string,
 * }> $peers
 */
function view_admin_peers_html(array $settings, array $peers, int $total, int $swarms, int $offset, int $limit, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/../functions/format.bytes.php';

    $actions = '<span class="ph-count"><b>'.number_format($total).'</b> active peers &middot; '.number_format($swarms).' swarm'.($swarms === 1 ? '' : 's').'</span>';

    $rows = '';
    foreach ($peers as $peer) {
        $client = '<span class="badge">'.htmlspecialchars($peer['client'], ENT_QUOTES, 'UTF-8').'</span>';

        // Registry name, or the truncated hash for an unregistered swarm.
        if ($peer['name'] !== null && $peer['name'] !== '') {
            $torrent = '<span class="muted nowrap">'.htmlspecialchars($peer['name'], ENT_QUOTES, 'UTF-8').'</span>';
        } else {
            $torrent = '<span class="mono dim">'.htmlspecialchars(substr($peer['info_hash'], 0, 12), ENT_QUOTES, 'UTF-8').'&hellip;</span>';
        }

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
            '<td><span class="flex items-center gap-2">'.$client.'</span></td>'.
            '<td>'.$torrent.'</td>'.
            '<td class="mono">'.$address.'</td>'.
            '<td>'.$state.'</td>'.
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
            ? '<a class="btn btn-ghost btn-sm" href="?page=peers&amp;offset='.max(0, $offset - $limit).'"><span class="ph-ico" data-lucide="arrow-left"></span>Previous</a>'
            : '<span class="btn btn-ghost btn-sm" aria-disabled="true"><span class="ph-ico" data-lucide="arrow-left"></span>Previous</span>';
        $next = $last < $total
            ? '<a class="btn btn-ghost btn-sm" href="?page=peers&amp;offset='.($offset + $limit).'">Next<span class="ph-ico" data-lucide="arrow-right"></span></a>'
            : '<span class="btn btn-ghost btn-sm" aria-disabled="true">Next<span class="ph-ico" data-lucide="arrow-right"></span></span>';
        $pager = '<div class="flex items-center gap-2 justify-end mt-4">'.$prev.$next.'</div>';
    }

    $body = '<div class="ph-toolbar">
			<span class="ph-search"><span class="ph-ico" data-lucide="search"></span><input type="search" aria-label="Search peers" placeholder="Search client, address, torrent&hellip;" oninput="phFilterTable(this, \'#tbl-peers\')"></span>
			<span class="ph-spacer"></span>
			<span class="dim text-sm">'.$window.'</span>
		</div>

		<div class="ph-card-table wide">
			<table id="tbl-peers">
				<thead><tr><th>Client</th><th>Torrent</th><th>Address</th><th>State</th><th class="table-col-numeric">Up</th><th class="table-col-numeric">Down</th><th class="table-col-numeric">Left</th><th>Last seen</th></tr></thead>
				<tbody>'.$rows.'</tbody>
			</table>
			<div class="ph-empty"'.($peers === [] ? '' : ' hidden').'>
				<span class="ph-ico" data-lucide="users"></span>
				<p>No active peers.</p>
			</div>
		</div>
		'.$pager;

    return view_admin_layout_html($settings, 'Peers', $body, 'peers', $csrf_token, 'Tracker', $actions, false);
}
