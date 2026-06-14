<?php

declare(strict_types=1);

////	view_admin_add_html
// Render the admin Add Torrent page: a form that accepts the torrent fields
// manually or a .torrent upload (drag-and-drop or file picker), which the
// controller parses to pre-fill every field. Needs installed tables to insert
// into; otherwise it points the operator at Utilities. Any action message is
// shown above. Wrapped in the shared admin layout. Returns HTML string.
//
// Parameters:
//   $settings - settings array
//   $tables_installed - bool, whether all tables are installed
//   $message - string|false, optional action-result message to display
//   $csrf_token - string, per-session token embedded in the form

/** @param PhoenixSettings $settings */
function view_admin_add_html(array $settings, bool $tables_installed, string|false $message, string $csrf_token): string
{
    // Hidden field carrying the CSRF token. Escaped defensively even though the
    // token is always hex.
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    $body = '<h1>Add a Torrent</h1>';

    if ($message) {
        $body .= '<div class="box background-wisteria color-clouds"><h3>'.htmlspecialchars($message).'</h3></div>';
    }

    if (! $tables_installed) {
        $body .= '<p class="box background-pomegranate color-clouds">The database is not installed yet. '.
            'Install it from <a href="?page=utilities">Utilities</a> before adding torrents.</p>';

        require_once __DIR__.'/html.admin.layout.php';

        return view_admin_layout_html($settings, 'Add a Torrent', $body, 'add', $csrf_token);
    }

    // enctype is multipart so the .torrent file input rides along; the parsed
    // file supplies the base for every field, with any explicit field
    // overriding it (see admin_torrent_add_action). No "mysql" class so the
    // layout's double-submit guard never interferes with the upload.
    $body .= '<form action="?page=add" method="POST" enctype="multipart/form-data">
			<p class="text-left">Name<br><input type="text" name="name"></p>
			<p class="text-left">Info Hash<br><input type="text" name="info_hash"></p>
			<p class="text-left">Size (bytes)<br><input type="number" name="size"></p>
			<p class="text-left"><input type="checkbox" name="listed" value="1" checked> Listed on the public index</p>
			<p class="text-left">Filename<br><input type="text" name="filename"></p>
			<p class="text-left">Files (JSON)<br><textarea name="files"></textarea></p>
			<p class="text-left">Trackers (one per line)<br><textarea name="trackers"></textarea></p>
			<p class="text-left">Web Seeds (one per line)<br><textarea name="webseeds"></textarea></p>
			<p class="text-left">Or drag &amp; drop / choose a .torrent file<br>
				<span id="torrent-drop" style="display:inline-block;border:2px dashed #bdc3c7;border-radius:.3em;padding:1em;cursor:pointer"><input type="file" name="torrent" id="torrent-file" accept=".torrent,application/x-bittorrent"><span id="torrent-drop-hint"></span></span>
			</p>
			<script>
			(function () {
				var zone = document.getElementById("torrent-drop");
				var input = document.getElementById("torrent-file");
				var hint = document.getElementById("torrent-drop-hint");
				if (!zone || !input) { return; }
				var paint = function (c) { zone.style.borderColor = c; };
				["dragenter", "dragover"].forEach(function (ev) {
					zone.addEventListener(ev, function (e) { e.preventDefault(); paint("#3498db"); });
				});
				["dragleave", "drop"].forEach(function (ev) {
					zone.addEventListener(ev, function (e) { e.preventDefault(); paint("#bdc3c7"); });
				});
				zone.addEventListener("drop", function (e) {
					if (e.dataTransfer && e.dataTransfer.files.length) {
						input.files = e.dataTransfer.files;
						if (hint) { hint.textContent = " " + input.files[0].name; }
					}
				});
				input.addEventListener("change", function () {
					if (hint) { hint.textContent = input.files.length ? " " + input.files[0].name : ""; }
				});
			})();
			</script>
			<input type="hidden" name="process" value="torrent_add">'.$csrf_field.'
			<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Add Torrent">
			<div class="clear"></div>
		</form>';

    require_once __DIR__.'/html.admin.layout.php';

    return view_admin_layout_html($settings, 'Add a Torrent', $body, 'add', $csrf_token);
}
