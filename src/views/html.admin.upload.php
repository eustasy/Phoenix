<?php

declare(strict_types=1);

////	view_admin_upload_html
// Render the admin Bulk Upload page: pick (or drop) many .torrent files, or a
// whole folder, and each is POSTed straight to the add API in the browser — no
// per-file form. The uploads use the admin session, so they carry the CSRF
// token the API's session path requires; without an admin password set there is
// no session/token, so the page explains that instead. Needs installed tables
// (the API would fail otherwise). Marks the Add nav active. Wrapped in the
// shared admin layout (narrow). Returns HTML string.
//
// Parameters:
//   $settings - settings array
//   $tables_installed - bool, whether all tables are installed
//   $csrf_token - per-session token; '' when no admin password is configured

/** @param PhoenixSettings $settings */
function view_admin_upload_html(array $settings, bool $tables_installed, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';

    $back = '<a class="btn btn-secondary btn-sm" href="?page=add"><span class="ph-ico" data-lucide="file-plus"></span>Single add</a>';

    if (! $tables_installed) {
        $body = '<div class="alert alert-danger"><span class="ph-ico" data-lucide="triangle-alert"></span><div>The database is not installed yet. Install it from <a href="?page=utilities">Utilities</a> before adding torrents.</div></div>';

        return view_admin_layout_html($settings, 'Bulk Upload', $body, 'add', $csrf_token, 'Tracker', $back, true);
    }

    // The uploads post to the authenticated add API, which accepts an admin
    // session only with a CSRF token — and that exists only with an admin
    // password set. Without one, point the operator at the alternatives.
    if ($csrf_token === '') {
        $body = '<div class="alert alert-warning"><span class="ph-ico" data-lucide="shield-alert"></span><div>Bulk upload sends each file to the authenticated add API, which needs a session token. Set an <strong>admin password</strong> on the <a href="?page=settings">Settings</a> page to enable it &mdash; or script <code>POST /api/torrent/add</code> with an API key instead.</div></div>';

        return view_admin_layout_html($settings, 'Bulk Upload', $body, 'add', $csrf_token, 'Tracker', $back, true);
    }

    $body = '<div id="bulk" data-csrf="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">
			<label class="ph-drop" id="bulk-drop">
				<span class="ph-ico" data-lucide="upload-cloud"></span>
				<div><strong>Drop .torrent files or a folder</strong> to add them all</div>
				<small class="dim">each file is sent straight to the add API &mdash; no form, no per-file editing</small>
			</label>

			<div class="ph-toolbar">
				<button type="button" class="btn btn-secondary btn-sm" id="bulk-files"><span class="ph-ico" data-lucide="files"></span>Choose files</button>
				<button type="button" class="btn btn-secondary btn-sm" id="bulk-folder"><span class="ph-ico" data-lucide="folder"></span>Choose folder</button>
				<span class="ph-spacer"></span>
				<label class="switch"><input type="checkbox" id="bulk-listed" role="switch" checked><span class="switch-track" aria-hidden="true"><span class="switch-thumb"></span></span><span class="switch-label">List on the public index</span></label>
			</div>

			<input type="file" id="bulk-file-input" accept=".torrent,application/x-bittorrent" multiple hidden>
			<input type="file" id="bulk-folder-input" webkitdirectory multiple hidden>

			<div id="bulk-summary" class="alert alert-info alert-center" hidden><span class="ph-ico" data-lucide="info"></span><div></div></div>

			<div id="bulk-results" class="ph-card-table" hidden>
				<table><thead><tr><th>File</th><th class="tar">Result</th></tr></thead><tbody></tbody></table>
			</div>
		</div>';

    $inline_js = <<<'JS'
        (function () {
          var root = document.getElementById('bulk');
          if (!root) return;
          var csrf = root.getAttribute('data-csrf') || '';
          var drop = document.getElementById('bulk-drop');
          var fileInput = document.getElementById('bulk-file-input');
          var folderInput = document.getElementById('bulk-folder-input');
          var listed = document.getElementById('bulk-listed');
          var summary = document.querySelector('#bulk-summary div');
          var summaryBox = document.getElementById('bulk-summary');
          var resultsWrap = document.getElementById('bulk-results');
          var tbody = document.querySelector('#bulk-results tbody');
          var counts = { added: 0, exists: 0, failed: 0, total: 0 };
          var queue = [];
          var busy = false;
          var stopped = false;
          var netFails = 0;

          document.getElementById('bulk-files').addEventListener('click', function () { fileInput.click(); });
          document.getElementById('bulk-folder').addEventListener('click', function () { folderInput.click(); });
          fileInput.addEventListener('change', function () { addFiles(toArray(fileInput.files)); fileInput.value = ''; });
          folderInput.addEventListener('change', function () { addFiles(toArray(folderInput.files)); folderInput.value = ''; });

          ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
          drop.addEventListener('dragleave', function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
          drop.addEventListener('drop', function (e) {
            e.preventDefault();
            drop.classList.remove('is-over');
            var items = e.dataTransfer.items;
            if (items && items.length && items[0].webkitGetAsEntry) {
              var entries = [];
              for (var i = 0; i < items.length; i++) { var en = items[i].webkitGetAsEntry(); if (en) entries.push(en); }
              Promise.all(entries.map(readEntry)).then(function (lists) { addFiles([].concat.apply([], lists)); });
            } else {
              addFiles(toArray(e.dataTransfer.files));
            }
          });

          function toArray(list) { return Array.prototype.slice.call(list || []); }

          // Recursively collect File objects from a dropped directory entry.
          function readEntry(entry) {
            return new Promise(function (resolve) {
              if (!entry) { resolve([]); return; }
              if (entry.isFile) { entry.file(function (f) { resolve([f]); }, function () { resolve([]); }); return; }
              if (entry.isDirectory) {
                var reader = entry.createReader();
                var acc = [];
                var readBatch = function () {
                  reader.readEntries(function (ents) {
                    if (!ents.length) {
                      Promise.all(acc.map(readEntry)).then(function (lists) { resolve([].concat.apply([], lists)); });
                      return;
                    }
                    acc = acc.concat(toArray(ents));
                    readBatch();
                  }, function () { resolve([]); });
                };
                readBatch();
                return;
              }
              resolve([]);
            });
          }

          function addFiles(files) {
            var torrents = files.filter(function (f) { return f && f.name && f.name.toLowerCase().slice(-8) === '.torrent'; });
            if (!torrents.length) return;
            resultsWrap.hidden = false;
            summaryBox.hidden = false;
            counts.total += torrents.length;
            torrents.forEach(function (f) {
              var tr = document.createElement('tr');
              var name = document.createElement('td');
              name.className = 'mono text-xs';
              name.textContent = f.name;
              var status = document.createElement('td');
              status.className = 'tar';
              status.innerHTML = '<span class="dim">Queued</span>';
              tr.appendChild(name);
              tr.appendChild(status);
              tbody.appendChild(tr);
              queue.push({ file: f, status: status });
            });
            updateSummary();
            if (!busy) pump();
          }

          async function pump() {
            busy = true;
            while (queue.length && !stopped) {
              var job = queue.shift();
              await upload(job.file, job.status);
            }
            // Server gave up (repeated connection failures): don't keep firing
            // at a dead server — mark the rest and tell the operator.
            if (stopped) {
              queue.forEach(function (job) { job.status.innerHTML = '<span class="dim">Not attempted</span>'; });
              queue.length = 0;
              summaryBox.className = 'alert alert-warning alert-center';
              summary.textContent = 'Stopped: the server stopped responding after ' + (counts.added + counts.exists + counts.failed) + ' of ' + counts.total + '. Reload and try a smaller batch.';
            }
            busy = false;
          }

          async function upload(file, status) {
            status.innerHTML = '<span class="dim">Uploading&hellip;</span>';
            var fd = new FormData();
            fd.append('torrent', file);
            fd.append('csrf', csrf);
            fd.append('listed', listed && listed.checked ? '1' : '0');
            try {
              var res = await fetch('/api/torrent/add.php', { method: 'POST', body: fd, credentials: 'same-origin' });
              var data = await res.json();
              netFails = 0; // reached the server and got a JSON reply
              if (data && data.torrent) {
                counts.added++;
                status.innerHTML = '<span class="badge badge-green">Added</span>';
              } else if (data && data.error === 'Torrent already exists.') {
                counts.exists++;
                status.innerHTML = '<span class="badge">Already present</span>';
              } else {
                counts.failed++;
                fail(status, (data && data.error) || 'Failed');
              }
            } catch (e) {
              // Network reset or non-JSON reply — the server is in trouble.
              counts.failed++;
              netFails++;
              fail(status, 'Server error');
              if (netFails >= 3) stopped = true;
            }
            updateSummary();
          }

          function updateSummary() {
            if (stopped) return; // pump() owns the final message once stopped
            var done = counts.added + counts.exists + counts.failed;
            summary.textContent = counts.added + ' added · ' + counts.exists + ' already present · ' + counts.failed + ' failed — ' + done + '/' + counts.total;
          }

          // Set a danger badge with an untrusted message via textContent.
          function fail(cell, msg) {
            cell.innerHTML = '';
            var b = document.createElement('span');
            b.className = 'badge text-danger';
            b.textContent = msg;
            cell.appendChild(b);
          }
        })();
        JS;

    return view_admin_layout_html($settings, 'Bulk Upload', $body, 'add', $csrf_token, 'Tracker', $back, true, '', $inline_js);
}
