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
        $body .= '<div class="alert alert-info" style="display:flex;gap:var(--space-2);align-items:flex-start"><span class="ph-ico" data-lucide="info" style="flex-shrink:0"></span><div>'.htmlspecialchars($message).'</div></div>';
    }

    if (! $tables_installed) {
        $body .= '<div class="alert alert-danger" style="display:flex;gap:var(--space-2);align-items:flex-start"><span class="ph-ico" data-lucide="triangle-alert" style="flex-shrink:0"></span><div>The database is not installed yet. Install it from <a href="?page=utilities">Utilities</a> before adding torrents.</div></div>';

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

		<div class="alert alert-danger" id="add-error" hidden style="display:flex;gap:var(--space-2);align-items:flex-start"><span class="ph-ico" data-lucide="circle-alert" style="flex-shrink:0"></span><div id="add-error-text"></div></div>

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
			<label class="checkbox" style="margin-block:var(--space-2)"><input type="checkbox" name="listed" value="1" checked><span class="checkbox-label">Listed on the public index</span></label>
			<input type="hidden" name="process" value="torrent_add">'.$csrf_field.'
			<div class="ph-form-actions">
				<button type="submit" name="submit" class="btn btn-primary"><span class="ph-ico" data-lucide="plus"></span>Add Torrent</button>
				<button type="reset" class="btn btn-ghost">Clear</button>
			</div>
		</form>';

    // Parse the dropped/picked .torrent client-side (PhoenixTorrent) and fill
    // the form fields; the operator can then amend any of them before adding.
    $inline_js = <<<'JS'
        (function () {
          var zone = document.getElementById('torrent-drop');
          var input = document.getElementById('torrent-file');
          var form = document.getElementById('add-form');
          var hint = document.getElementById('torrent-drop-hint');
          var errorEl = document.getElementById('add-error');
          var errorText = document.getElementById('add-error-text');
          if (!zone || !input || !form || typeof PhoenixTorrent === 'undefined') return;

          function setField(name, value) { var el = form.elements[name]; if (el) el.value = value; }
          function showError(msg) { if (errorText) errorText.textContent = msg; if (errorEl) errorEl.hidden = false; }

          async function handleFile(file) {
            if (errorEl) errorEl.hidden = true;
            if (!file || !file.name.endsWith('.torrent')) { showError('Please drop a .torrent file.'); return; }
            if (hint) hint.textContent = ' — ' + file.name;
            try {
              var info = await PhoenixTorrent.torrentInfo(await file.arrayBuffer());
              setField('info_hash', info.infoHash);
              setField('name', info.name);
              setField('size', info.size || '');
              setField('filename', info.filename);
              setField('files', info.files.length ? JSON.stringify(info.files) : '');
              setField('trackers', info.trackers.join('\n'));
              setField('webseeds', info.webseeds.join('\n'));
            } catch (e) { showError(e.message); }
          }

          ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); }); });
          ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('is-over'); }); });
          zone.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]); });
          input.addEventListener('change', function () { if (input.files.length) handleFile(input.files[0]); });
        })();
        JS;

    $actions = '<a class="btn btn-secondary btn-sm" href="?page=upload"><span class="ph-ico" data-lucide="upload"></span>Bulk upload</a>';

    return view_admin_layout_html($settings, 'Add a Torrent', $body, 'add', $csrf_token, 'Tracker', $actions, true, '', $inline_js, ['/assets/torrent-parse.js']);
}
