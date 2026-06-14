<?php

declare(strict_types=1);

////	view_admin_torrent_peers_html
// Render the admin peer drill-down: one torrent's swarm as a table of client,
// address, state, transfer totals, and last-seen time. The title shows the
// registry name when known, otherwise the raw info_hash (an unregistered
// swarm). All untrusted strings (client, IPs) are htmlspecialchars()-escaped.
// Marks the Torrents nav active since this is a drill-down beneath it. Wrapped
// in the shared admin layout (wide variant). Returns HTML string.

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
    $title = $name !== null && $name !== ''
        ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        : '<code>'.htmlspecialchars($info_hash, ENT_QUOTES, 'UTF-8').'</code>';

    $body = '<h1>Peers</h1>
		<p class="text-left">'.$title.'</p>
		<p class="text-left"><a href="?page=torrents">&larr; Back to Torrents</a></p>';

    if ($peers === []) {
        $body .= '<p class="box background-clouds">No active peers.</p>';
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
            $address = $addrs === [] ? '&mdash;' : implode('<br>', $addrs);

            $rows .= '<tr>'.
                '<td>'.htmlspecialchars($peer['client'], ENT_QUOTES, 'UTF-8').'</td>'.
                '<td>'.$address.'</td>'.
                '<td>'.($peer['state'] === 1 ? 'Seeding' : 'Leeching').'</td>'.
                '<td>'.number_format($peer['uploaded']).'</td>'.
                '<td>'.number_format($peer['downloaded']).'</td>'.
                '<td>'.number_format($peer['left']).'</td>'.
                '<td>'.date('Y-m-d H:i', $peer['updated']).'</td>'.
                '</tr>';
        }

        $body .= '<table class="data-table">'.
            '<thead><tr>'.
            '<th>Client</th><th>Address</th><th>State</th>'.
            '<th>Up</th><th>Down</th><th>Left</th><th>Last seen</th>'.
            '</tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    require_once __DIR__.'/html.admin.layout.php';

    return view_admin_layout_html($settings, 'Peers', $body, 'torrents', $csrf_token, true);
}
