<?php

declare(strict_types=1);

////	view_admin_add_html
// Render the admin Add Torrent page: a form that accepts the torrent fields
// manually or a .torrent upload (drag-and-drop or file picker), which the
// controller parses server-side to fill any field the form left blank. Needs
// installed tables to insert into; otherwise it points the operator at
// Utilities. Any action message is shown above. Wrapped in the shared admin
// layout (narrow). Returns HTML string.
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
        $body .= '<div class="alert alert-info" style="display:flex;gap:var(--space-2);align-items:flex-start"><span class="ph-ico" data-lucide="info" style="flex-shrink:0"></span><div>'.htmlspecialchars($message).'</div></div>';
    }

    if (! $tables_installed) {
        $body .= '<div class="alert alert-danger" style="display:flex;gap:var(--space-2);align-items:flex-start"><span class="ph-ico" data-lucide="triangle-alert" style="flex-shrink:0"></span><div>The database is not installed yet. Install it from <a href="?page=utilities">Utilities</a> before adding torrents.</div></div>';

        return view_admin_layout_html($settings, 'Add a Torrent', $body, 'add', $csrf_token, 'Tracker', '', true);
    }

    // enctype is multipart so the .torrent file input rides along; the parsed
    // file supplies the base for every field, with any explicit field
    // overriding it (see admin_torrent_add_action). No "mysql" class so the
    // layout's double-submit guard never interferes with the upload.
    $body .= '<label class="ph-drop" id="torrent-drop">
			<span class="ph-ico" data-lucide="upload-cloud"></span>
			<div><strong>Drop a .torrent file</strong> to fill the form automatically</div>
			<small class="dim">or click to browse &mdash; parsed on the server when you submit<span id="torrent-drop-hint"></span></small>
			<input type="file" name="torrent" id="torrent-file" accept=".torrent,application/x-bittorrent" form="add-form">
		</label>

		<form id="add-form" class="ph-form-card" action="?page=add" method="POST" enctype="multipart/form-data">
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
			<label class="checkbox" style="margin-block:var(--space-2)"><input type="checkbox" name="listed" value="1" checked><span class="checkbox-label">Listed on the public index</span></label>
			<input type="hidden" name="process" value="torrent_add">'.$csrf_field.'
			<div class="ph-form-actions">
				<button type="submit" name="submit" class="btn btn-primary"><span class="ph-ico" data-lucide="plus"></span>Add Torrent</button>
				<button type="reset" class="btn btn-ghost">Clear</button>
			</div>
		</form>
		<script>
		(function () {
			var zone = document.getElementById("torrent-drop");
			var input = document.getElementById("torrent-file");
			var hint = document.getElementById("torrent-drop-hint");
			if (!zone || !input) { return; }
			["dragenter", "dragover"].forEach(function (ev) {
				zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add("is-over"); });
			});
			["dragleave", "drop"].forEach(function (ev) {
				zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove("is-over"); });
			});
			zone.addEventListener("drop", function (e) {
				if (e.dataTransfer && e.dataTransfer.files.length) {
					input.files = e.dataTransfer.files;
					if (hint) { hint.textContent = " — " + input.files[0].name; }
				}
			});
			input.addEventListener("change", function () {
				if (hint) { hint.textContent = input.files.length ? " — " + input.files[0].name : ""; }
			});
		})();
		</script>';

    return view_admin_layout_html($settings, 'Add a Torrent', $body, 'add', $csrf_token, 'Tracker', '', true);
}
