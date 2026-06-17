<?php

declare(strict_types=1);

////	view_admin_add_html
// Render the admin Add Torrent page: a form for the torrent fields, with a
// drop zone that parses a dropped/picked .torrent IN THE BROWSER (the same way
// the magnet generator does) and fills the form, so the operator can amend
// anything before submitting. The form then posts the fields — not the file —
// so no separate edit round-trip is needed. (The server still accepts a
// multipart upload too, e.g. from the API.) Needs installed tables to insert
// into; otherwise it points the operator at Utilities. Any action message is
// shown above. Wrapped in the shared admin layout (narrow). Returns HTML string.
//
// Parameters:
//   $settings - settings array
//   $tables_installed - bool, whether all tables are installed
//   $message - string|false, optional action-result message to display
//   $csrf_token - string, per-session token embedded in the form

/** @param PhoenixSettings $settings */
function view_admin_add_html(array $settings, bool $tables_installed, string|false $message, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';

    // Hidden field carrying the CSRF token. Escaped defensively even though the
    // token is always hex.
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    $body = '';

    if ($message) {
        $body .= '<div class="alert alert-info"><span class="ph-ico" data-lucide="info"></span><div>'.htmlspecialchars($message).'</div></div>';
    }

    if (! $tables_installed) {
        $body .= '<div class="alert alert-danger"><span class="ph-ico" data-lucide="triangle-alert"></span><div>The database is not installed yet. Install it from <a href="?page=utilities">Utilities</a> before adding torrents.</div></div>';

        return view_admin_layout_html($settings, 'Add a Torrent', $body, 'add', $csrf_token, 'Tracker', '', true);
    }

    // The drop zone parses the file client-side and fills the form below; the
    // file itself is never uploaded from here. The form posts plain fields.
    $body .= '<label class="ph-drop" id="torrent-drop">
			<span class="ph-ico" data-lucide="upload-cloud"></span>
			<div><strong>Drop a .torrent file</strong> to fill the form automatically</div>
			<small class="dim">or click to browse &mdash; parsed in your browser, nothing is uploaded<span id="torrent-drop-hint"></span></small>
			<input type="file" id="torrent-file" accept=".torrent,application/x-bittorrent">
		</label>

		<div class="alert alert-danger" id="add-error" hidden><span class="ph-ico" data-lucide="circle-alert"></span><div id="add-error-text"></div></div>

		<form id="add-form" class="ph-form-card" action="?page=add" method="POST">
			<div class="ph-field-row">
				<div class="ph-field"><label>Name</label><input type="text" name="name"></div>
				<div class="ph-field"><label>Size (bytes)</label><input type="number" name="size"></div>
			</div>
			<div class="ph-field"><label>Info hash</label><input type="text" name="info_hash" class="mono"></div>
			<div class="ph-field"><label>Filename</label><input type="text" name="filename"></div>
			<div class="ph-field"><label>Files (JSON)</label><textarea name="files" class="code" rows="3"></textarea></div>
			<div class="ph-field-row">
				<div class="ph-field"><label>Trackers (one per line)</label><textarea name="trackers" class="code" rows="3"></textarea></div>
				<div class="ph-field"><label>Web seeds (one per line)</label><textarea name="webseeds" class="code" rows="3"></textarea></div>
			</div>
			<label class="checkbox my-2"><input type="checkbox" name="listed" value="1" checked><span class="checkbox-label">Listed on the public index</span></label>
			<input type="hidden" name="process" value="torrent_add">'.$csrf_field.'
			<div class="ph-form-actions">
				<button type="submit" name="submit" class="btn btn-primary"><span class="ph-ico" data-lucide="plus"></span>Add Torrent</button>
				<button type="reset" class="btn btn-ghost">Clear</button>
			</div>
		</form>';

    // Drag/drop parsing + form-fill lives in assets/add.js, which uses
    // PhoenixTorrent (assets/torrent-parse.js); both load as page sources.
    $actions = '<a class="btn btn-secondary btn-sm" href="?page=upload"><span class="ph-ico" data-lucide="upload"></span>Bulk upload</a>';

    return view_admin_layout_html($settings, 'Add a Torrent', $body, 'add', $csrf_token, 'Tracker', $actions, true, '', '', ['/assets/torrent-parse.js', '/assets/add.js']);
}
