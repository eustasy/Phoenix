<?php

declare(strict_types=1);

////	view_admin_torrents_html
// Render the admin Torrents management page: a full-width table of every
// torrent with swarm stats, and per-row List/Unlist + Delete forms (each a
// CSRF-protected POST carrying the info_hash). Untrusted strings (name, owner)
// are htmlspecialchars()-escaped; info_hash is validated 40-char hex. Wrapped
// in the shared admin layout in its wide variant. Returns HTML string.
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
 */
function view_admin_torrents_html(array $settings, array $torrents, string|false $message, string $csrf_token): string
{
    // Hidden field carrying the CSRF token, embedded in every action form.
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    $body = '<h1>Torrents</h1>';

    if ($message) {
        $body .= '<div class="box background-wisteria color-clouds"><h3>'.htmlspecialchars($message).'</h3></div>';
    }

    if ($torrents === []) {
        $body .= '<p class="box background-clouds">No torrents are registered.</p>';
    } else {
        $rows = '';
        foreach ($torrents as $torrent) {
            $info_hash = (string) $torrent['info_hash'];
            $name = htmlspecialchars((string) ($torrent['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $owner = $torrent['user'] === null
                ? '&mdash;'
                : htmlspecialchars($torrent['user'], ENT_QUOTES, 'UTF-8');
            $listed = $torrent['listed'];

            // Toggle form sends the OPPOSITE of the current listed state.
            $toggle_form = '<form method="POST" style="display:inline">'.
                '<input type="hidden" name="process" value="torrent_listed">'.
                '<input type="hidden" name="info_hash" value="'.$info_hash.'">'.
                '<input type="hidden" name="listed" value="'.($listed === 1 ? 0 : 1).'">'.$csrf_field.
                '<button type="submit" class="button background-belize-hole color-clouds">'.
                ($listed === 1 ? 'Unlist' : 'List').'</button></form>';

            $delete_form = '<form method="POST" style="display:inline" '.
                'onsubmit="return confirm(\'Delete this torrent and its peers?\')">'.
                '<input type="hidden" name="process" value="torrent_delete">'.
                '<input type="hidden" name="info_hash" value="'.$info_hash.'">'.$csrf_field.
                '<button type="submit" class="button background-pomegranate color-clouds">Delete</button></form>';

            $rows .= '<tr>'.
                '<td>'.$name.'</td>'.
                '<td>'.$owner.'</td>'.
                '<td><code>'.$info_hash.'</code></td>'.
                '<td>'.number_format($torrent['size']).'</td>'.
                '<td>'.number_format($torrent['seeders']).'</td>'.
                '<td>'.number_format($torrent['leechers']).'</td>'.
                '<td>'.number_format($torrent['downloads']).'</td>'.
                '<td>'.number_format($torrent['traffic']).'</td>'.
                '<td>'.($listed === 1 ? 'Listed' : 'Unlisted').'</td>'.
                '<td>'.$toggle_form.' '.$delete_form.'</td>'.
                '</tr>';
        }

        $body .= '<table class="data-table">'.
            '<thead><tr>'.
            '<th>Name</th><th>Owner</th><th>Info Hash</th><th>Size</th>'.
            '<th>Seeders</th><th>Leechers</th><th>Downloads</th><th>Traffic</th>'.
            '<th>Listed</th><th>Actions</th>'.
            '</tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    require_once __DIR__.'/html.admin.layout.php';

    return view_admin_layout_html($settings, 'Torrents', $body, 'torrents', $csrf_token, true);
}
