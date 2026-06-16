<?php

declare(strict_types=1);

////	Magnet Generator
// A self-contained, client-side magnet builder. Deliberately does NOT bootstrap
// src/phoenix.php — it never touches the tracker or database, so it works even
// when the tracker is misconfigured. It reuses only the pure view partials for
// the shared page chrome.

header('Content-Type: text/html; charset=UTF-8');

// Derive the local announce URL from the current request to pre-fill the
// tracker field. Escaped for safety, then JSON-encoded into the inline script.
$announce_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    .'://'.htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES)
    .rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/magnet.php'), '/\\')
    .'/announce.php';

require_once __DIR__.'/../src/views/html.public.layout.php';

$extra_head = '
	<link rel="stylesheet" href="/assets/magnet.css">';

$body = '<div class="ph-page-title">
		<div>
			<h1>Magnet Generator</h1>
			<p>Drop a <code>.torrent</code> and build a magnet link &mdash; everything runs in your browser, nothing is uploaded.</p>
		</div>
	</div>

	<label class="ph-drop" id="drop-zone" for="file-input">
		<span class="ph-ico" data-lucide="file-down"></span>
		<div><strong>Drop a .torrent file here</strong></div>
		<small class="dim">or click to browse</small>
		<input type="file" id="file-input" accept=".torrent">
	</label>

	<div class="alert alert-danger" id="error" hidden><span style="display:flex;gap:var(--space-2);align-items:center"><span class="ph-ico" data-lucide="circle-alert"></span><span id="error-text"></span></span></div>

	<section id="results" hidden>
		<div class="ph-form-card">
			<div class="ph-field-row">
				<div class="ph-field"><label>xt &mdash; Info Hash</label><input type="text" class="mono" id="f-xt" readonly></div>
				<div class="ph-field"><label>xl &mdash; Size (bytes)</label><input type="number" id="f-xl" min="0"></div>
			</div>
			<div class="ph-field"><label>dn &mdash; Display Name</label><input type="text" id="f-dn"></div>
			<div class="ph-field-row">
				<div class="ph-field"><label>tr &mdash; Trackers (one per line)</label><textarea class="code" id="f-tr" rows="3"></textarea></div>
				<div class="ph-field"><label>ws &mdash; Web Seeds (one per line)</label><textarea class="code" id="f-ws" rows="3"></textarea></div>
			</div>
			<div class="ph-field-row">
				<div class="ph-field"><label>xs &mdash; Exact Source (one per line)</label><textarea class="code" id="f-xs" rows="2"></textarea></div>
				<div class="ph-field"><label>as &mdash; Acceptable Source (one per line)</label><textarea class="code" id="f-as" rows="2"></textarea></div>
			</div>
			<div class="ph-field"><label>kt &mdash; Keyword Topic (space-separated)</label><input type="text" id="f-kt"></div>

			<div class="ph-field" style="margin-bottom:0">
				<label>Magnet Link</label>
				<div class="magnet-output-wrap">
					<textarea id="magnet-out" class="magnet-out" readonly rows="4"></textarea>
					<button class="btn btn-primary btn-sm copy-float" id="copy-btn"><span class="ph-ico" data-lucide="copy"></span>Copy</button>
				</div>
			</div>
		</div>
	</section>';

// Client-side bencode parser + magnet builder. ANNOUNCE is defined first and
// closed over by the IIFE. Logic is unchanged from the original generator.
$parser_js = <<<'JS'
    (function () {
      function parseTorrent(arrayBuffer) {
        const bytes = new Uint8Array(arrayBuffer);
        let pos = 0;
        function readDigits(stopCode) { let s = ''; while (bytes[pos] !== stopCode) s += String.fromCharCode(bytes[pos++]); pos++; return s; }
        function parseString() { const len = parseInt(readDigits(58), 10); const slice = bytes.slice(pos, pos + len); pos += len; return slice; }
        function parseValue() {
          const b = bytes[pos];
          if (b === 105) { pos++; return parseInt(readDigits(101), 10); }
          if (b === 108) { pos++; const arr = []; while (bytes[pos] !== 101) arr.push(parseValue()); pos++; return arr; }
          if (b === 100) { pos++; const obj = {}; while (bytes[pos] !== 101) { const key = decode(parseString()); obj[key] = parseValue(); } pos++; return obj; }
          if (b >= 48 && b <= 57) return parseString();
          throw new Error('Unexpected byte ' + b + ' at position ' + pos);
        }
        if (bytes[pos++] !== 100) throw new Error('Torrent file is not a bencode dict.');
        const torrent = {}; let infoStart = -1, infoEnd = -1;
        while (bytes[pos] !== 101) {
          const key = decode(parseString());
          if (key === 'info') infoStart = pos;
          torrent[key] = parseValue();
          if (key === 'info' && infoStart >= 0) infoEnd = pos;
        }
        if (infoStart < 0) throw new Error('No info dict found in torrent file.');
        return { torrent, infoBytes: arrayBuffer.slice(infoStart, infoEnd) };
      }

      function decode(bytes) { try { return new TextDecoder('utf-8').decode(bytes); } catch (e) { return ''; } }
      async function sha1hex(buffer) {
        if (!crypto || !crypto.subtle) throw new Error('SHA-1 requires a secure context (HTTPS or localhost).');
        const hash = await crypto.subtle.digest('SHA-1', buffer);
        return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
      }
      function extractInfo(torrent) {
        const info = torrent.info || {};
        const nameBytes = info['name.utf-8'] || info['name'];
        const name = nameBytes instanceof Uint8Array ? decode(nameBytes) : '';
        let size = 0;
        if (typeof info.length === 'number') size = info.length;
        else if (Array.isArray(info.files)) for (const f of info.files) if (typeof f.length === 'number') size += f.length;
        const trackers = [ANNOUNCE];
        const add = (url) => { if (url && !trackers.includes(url)) trackers.push(url); };
        if (torrent.announce) add(decode(torrent.announce));
        if (Array.isArray(torrent['announce-list'])) for (const tier of torrent['announce-list']) if (Array.isArray(tier)) for (const u of tier) add(decode(u));
        const webSeeds = [], exactSources = [];
        const addSeed = (u) => { const s = decode(u); if (s) { webSeeds.push(s); exactSources.push(s); } };
        if (torrent['url-list']) { const ul = torrent['url-list']; if (ul instanceof Uint8Array) addSeed(ul); else if (Array.isArray(ul)) for (const u of ul) addSeed(u); }
        return { name, size, trackers, webSeeds, exactSources };
      }
      function buildMagnet(hash, name, size, trackers, webSeeds, exactSources, acceptableSources, keywords) {
        let m = 'magnet:?xt=urn:btih:' + hash;
        if (name) m += '&dn=' + encodeURIComponent(name);
        if (size) m += '&xl=' + size;
        for (const tr of trackers) if (tr.trim()) m += '&tr=' + encodeURIComponent(tr.trim());
        for (const ws of webSeeds) if (ws.trim()) m += '&ws=' + encodeURIComponent(ws.trim());
        for (const xs of exactSources) if (xs.trim()) m += '&xs=' + encodeURIComponent(xs.trim());
        for (const as of acceptableSources) if (as.trim()) m += '&as=' + encodeURIComponent(as.trim());
        if (keywords.trim()) m += '&kt=' + encodeURIComponent(keywords.trim());
        return m;
      }

      const dropZone = document.getElementById('drop-zone'), fileInput = document.getElementById('file-input');
      const errorEl = document.getElementById('error'), errorText = document.getElementById('error-text'), results = document.getElementById('results');
      const fXt = document.getElementById('f-xt'), fDn = document.getElementById('f-dn'), fXl = document.getElementById('f-xl');
      const fTr = document.getElementById('f-tr'), fWs = document.getElementById('f-ws'), fXs = document.getElementById('f-xs');
      const fAs = document.getElementById('f-as'), fKt = document.getElementById('f-kt');
      const magnetOut = document.getElementById('magnet-out'), copyBtn = document.getElementById('copy-btn');

      function showError(msg) { errorText.textContent = msg; errorEl.hidden = false; results.hidden = true; }
      function updateMagnet() {
        const hash = fXt.value.trim();
        magnetOut.value = hash ? buildMagnet(hash, fDn.value.trim(), parseInt(fXl.value,10)||0, fTr.value.split('\n'), fWs.value.split('\n'), fXs.value.split('\n'), fAs.value.split('\n'), fKt.value) : '';
      }
      async function handleFile(file) {
        errorEl.hidden = true;
        if (!file || !file.name.endsWith('.torrent')) { showError('Please drop a .torrent file.'); return; }
        try {
          const buffer = await file.arrayBuffer();
          const { torrent, infoBytes } = parseTorrent(buffer);
          const hash = await sha1hex(infoBytes);
          const { name, size, trackers, webSeeds, exactSources } = extractInfo(torrent);
          fXt.value = hash; fDn.value = name; fXl.value = size || '';
          fTr.value = trackers.join('\n'); fWs.value = webSeeds.join('\n'); fXs.value = exactSources.join('\n');
          updateMagnet(); results.hidden = false;
        } catch (e) { showError(e.message); }
      }
      dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('is-over'); });
      dropZone.addEventListener('dragleave', () => dropZone.classList.remove('is-over'));
      dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.classList.remove('is-over'); handleFile(e.dataTransfer.files[0]); });
      fileInput.addEventListener('change', () => handleFile(fileInput.files[0]));
      [fDn, fXl, fTr, fWs, fXs, fAs, fKt].forEach(el => el.addEventListener('input', updateMagnet));
      copyBtn.addEventListener('click', () => {
        navigator.clipboard.writeText(magnetOut.value).then(() => {
          copyBtn.innerHTML = '<span class="ph-ico" data-lucide="check"></span>Copied'; phInitIcons();
          setTimeout(() => { copyBtn.innerHTML = '<span class="ph-ico" data-lucide="copy"></span>Copy'; phInitIcons(); }, 1500);
        });
      });
    })();
    JS;

$inline_js = 'const ANNOUNCE = '.json_encode($announce_url).";\n".$parser_js;

echo view_public_layout_html('Magnet Generator — Phoenix', $body, 'magnet', '', true, $extra_head, $inline_js);
