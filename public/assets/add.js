/* Phoenix — Add Torrent drag/drop (assets/add.js).
 * Parses a dropped/picked .torrent IN THE BROWSER (via PhoenixTorrent) and fills
 * the add form so the operator can amend any field before submitting; the file
 * itself is never uploaded from here. */
/* global PhoenixTorrent */

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
