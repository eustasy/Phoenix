<?php

declare(strict_types=1);

////	view_admin_edit_html
// Render the admin Edit Torrent page: a form pre-filled with a torrent's
// current fields, posting back to itself (process=torrent_edit) for a full
// replace. info_hash is shown read-only — it identifies the row and can't
// change. The stored meta is rendered back into its request shape (files as
// JSON, trackers/webseeds newline-joined) so it round-trips through
// sanitize_torrent_meta on submit. Every value is htmlspecialchars()-escaped.
// When the torrent is missing, a notice replaces the form. Marks the Torrents
// nav active (this is a drill-down beneath it). Wrapped in the shared admin
// layout (wide variant). Returns HTML string.

/**
 * @param PhoenixSettings $settings
 * @param array{
 *     info_hash: string,
 *     user: string|null,
 *     name: string|null,
 *     size: int,
 *     listed: int,
 *     downloads: int,
 *     filename: string|null,
 *     files: list<array{path: string, length: int}>|null,
 *     trackers: list<string>|null,
 *     webseeds: list<string>|null,
 * }|false $torrent
 */
function view_admin_edit_html(array $settings, string $info_hash, array|false $torrent, string|false $message, string $csrf_token): string
{
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';
    $hash = htmlspecialchars($info_hash, ENT_QUOTES, 'UTF-8');

    $body = '<h1>Edit Torrent</h1>
		<p class="text-left"><a href="?page=torrents">&larr; Back to Torrents</a></p>';

    if ($message) {
        $body .= '<div class="box background-wisteria color-clouds"><h3>'.htmlspecialchars($message).'</h3></div>';
    }

    if ($torrent === false) {
        $body .= '<p class="box background-pomegranate color-clouds">Torrent not found.</p>';

        require_once __DIR__.'/html.admin.layout.php';

        return view_admin_layout_html($settings, 'Edit Torrent', $body, 'torrents', $csrf_token, true);
    }

    // Render the stored meta back into the request shape the form submits.
    $name = htmlspecialchars((string) ($torrent['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $filename = htmlspecialchars((string) ($torrent['filename'] ?? ''), ENT_QUOTES, 'UTF-8');
    // Unescaped slashes/unicode keep the JSON readable for hand-editing; it
    // still decodes cleanly back through sanitize_torrent_meta on submit.
    $files = $torrent['files'] === null
        ? ''
        : htmlspecialchars(
            (string) json_encode($torrent['files'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
            'UTF-8',
        );
    $trackers = $torrent['trackers'] === null
        ? ''
        : htmlspecialchars(implode("\n", $torrent['trackers']), ENT_QUOTES, 'UTF-8');
    $webseeds = $torrent['webseeds'] === null
        ? ''
        : htmlspecialchars(implode("\n", $torrent['webseeds']), ENT_QUOTES, 'UTF-8');
    $checked = $torrent['listed'] === 1 ? ' checked' : '';

    $body .= '<form class="mysql" action="?page=edit&amp;info_hash='.$hash.'" method="POST">
			<input type="hidden" name="process" value="torrent_edit">
			<input type="hidden" name="info_hash" value="'.$hash.'">'.$csrf_field.'
			<p class="text-left">Info Hash<br><code>'.$hash.'</code></p>
			<p class="text-left">Name<br><input type="text" name="name" value="'.$name.'"></p>
			<p class="text-left">Size (bytes)<br><input type="number" name="size" value="'.intval($torrent['size']).'"></p>
			<p class="text-left"><input type="checkbox" name="listed" value="1"'.$checked.'> Listed on the public index</p>
			<p class="text-left">Filename<br><input type="text" name="filename" value="'.$filename.'"></p>
			<p class="text-left">Files (JSON)<br><textarea name="files">'.$files.'</textarea></p>
			<p class="text-left">Trackers (one per line)<br><textarea name="trackers">'.$trackers.'</textarea></p>
			<p class="text-left">Web Seeds (one per line)<br><textarea name="webseeds">'.$webseeds.'</textarea></p>
			<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Save Changes">
			<div class="clear"></div>
		</form>';

    require_once __DIR__.'/html.admin.layout.php';

    return view_admin_layout_html($settings, 'Edit Torrent', $body, 'torrents', $csrf_token, true);
}
