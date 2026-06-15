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
// layout (narrow). Returns HTML string.

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
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/html.hash.php';

    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';
    $hash = htmlspecialchars($info_hash, ENT_QUOTES, 'UTF-8');
    $back = '<a class="btn btn-secondary btn-sm" href="?page=torrents"><span class="ph-ico" data-lucide="arrow-left"></span>Back</a>';

    $message_html = $message
        ? '<div class="alert alert-info" style="display:flex;gap:var(--space-2);align-items:flex-start"><span class="ph-ico" data-lucide="info" style="flex-shrink:0"></span><div>'.htmlspecialchars($message).'</div></div>'
        : '';

    if ($torrent === false) {
        $body = $message_html.'<div class="alert alert-danger" style="display:flex;gap:var(--space-2);align-items:center"><span class="ph-ico" data-lucide="circle-alert"></span>Torrent not found.</div>';

        return view_admin_layout_html($settings, 'Edit Torrent', $body, 'torrents', $csrf_token, 'Tracker', $back, true);
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

    $body = $message_html.'
		<div class="ph-form-card">
			<div class="ph-field"><label>Info hash <span class="dim">(read-only)</span></label>
				<div class="hash" style="background:var(--color-code-bg);border:1px solid var(--color-code-border);border-radius:var(--radius-md);padding:var(--space-2) var(--space-3);width:100%"><span class="hash-text" style="max-width:none;font-size:var(--font-size-sm)">'.$hash.'</span></div>
			</div>
			<form class="mysql" action="?page=edit&amp;info_hash='.$hash.'" method="POST">
				<input type="hidden" name="process" value="torrent_edit">
				<input type="hidden" name="info_hash" value="'.$hash.'">'.$csrf_field.'
				<div class="ph-field-row">
					<div class="ph-field"><label>Name</label><input type="text" name="name" value="'.$name.'"></div>
					<div class="ph-field"><label>Size (bytes)</label><input type="number" name="size" value="'.intval($torrent['size']).'"></div>
				</div>
				<div class="ph-field"><label>Filename</label><input type="text" name="filename" value="'.$filename.'"></div>
				<div class="ph-field"><label>Files (JSON)</label><textarea name="files" class="code" rows="3">'.$files.'</textarea></div>
				<div class="ph-field-row">
					<div class="ph-field"><label>Trackers (one per line)</label><textarea name="trackers" class="code" rows="3">'.$trackers.'</textarea></div>
					<div class="ph-field"><label>Web seeds (one per line)</label><textarea name="webseeds" class="code" rows="3">'.$webseeds.'</textarea></div>
				</div>
				<label class="checkbox" style="margin-block:var(--space-2)"><input type="checkbox" name="listed" value="1"'.$checked.'><span class="checkbox-label">Listed on the public index</span></label>
				<div class="ph-form-actions">
					<button type="submit" name="submit" class="btn btn-primary"><span class="ph-ico" data-lucide="save"></span>Save Changes</button>
					<a class="btn btn-ghost" href="?page=torrents">Cancel</a>
				</div>
			</form>
		</div>';

    return view_admin_layout_html($settings, 'Edit Torrent', $body, 'torrents', $csrf_token, 'Tracker', $back, true);
}
