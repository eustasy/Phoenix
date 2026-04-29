<?php
// Derives the local announce URL from the current request context to pre-fill the tracker field.
$announce_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
	. '://' . htmlspecialchars($_SERVER['HTTP_HOST'], ENT_QUOTES)
	. rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')
	. '/announce.php';
?><!DOCTYPE html>
<html lang="en">
<head>
	<title>Phoenix &mdash; Magnet Generator</title>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/combine/gh/eustasy/Colors.css@1/colors.min.css,gh/necolas/normalize.css@8/normalize.min.css">
	<style>
		body        { margin: 0 auto; max-width: 640px; padding: 1% 10%; width: 80%; }
		h1, h2      { font-weight: normal; }
		a           { text-decoration: none; }
		input[type="text"],
		input[type="number"],
		textarea    { border: 1px solid #bdc3c7; border-radius: .2em; box-sizing: border-box; padding: .4em; width: 100%; }
		textarea    { font-family: monospace; resize: vertical; }
		button      { border: none; border-radius: .2em; cursor: pointer; padding: .4em .8em; }
		.box        { padding: 1em; }
		.field      { margin: .8em 0; }
		.field label { color: #7f8c8d; display: block; font-size: .9em; margin-bottom: .2em; }
		#drop-zone  {
			border: 2px dashed #bdc3c7;
			border-radius: .4em;
			cursor: pointer;
			margin: 1.5em 0;
			padding: 2.5em 1em;
			text-align: center;
			transition: border-color .15s, background .15s;
		}
		#drop-zone.over { background: #eaf4fb; border-color: #2980b9; }
		#drop-zone small { color: #7f8c8d; }
		#drop-zone input[type="file"] { display: none; }
		#error      { display: none; }
		#results    { display: none; }
		#magnet-out { min-height: 5em; word-break: break-all; }
	</style>
</head>
<body>

<h1>Magnet Generator</h1>

<div id="drop-zone">
	<p>Drop a <code>.torrent</code> file here</p>
	<small>or <label for="file-input" style="color:#2980b9;cursor:pointer">browse</label></small>
	<input type="file" id="file-input" accept=".torrent">
</div>

<p id="error" class="box background-pomegranate color-clouds"></p>

<section id="results">
	<div class="field">
		<label>xt &mdash; Info Hash</label>
		<input type="text" id="f-xt" readonly>
	</div>
	<div class="field">
		<label>dn &mdash; Display Name</label>
		<input type="text" id="f-dn">
	</div>
	<div class="field">
		<label>xl &mdash; Size (bytes)</label>
		<input type="number" id="f-xl" min="0">
	</div>
	<div class="field">
		<label>tr &mdash; Trackers (one per line)</label>
		<textarea id="f-tr" rows="3"></textarea>
	</div>
	<div class="field">
		<label>ws &mdash; Web Seeds (one per line)</label>
		<textarea id="f-ws" rows="2"></textarea>
	</div>
	<div class="field">
		<label>xs &mdash; Exact Source (one per line)</label>
		<textarea id="f-xs" rows="2"></textarea>
	</div>
	<div class="field">
		<label>as &mdash; Acceptable Source (one per line)</label>
		<textarea id="f-as" rows="2"></textarea>
	</div>
	<div class="field">
		<label>kt &mdash; Keyword Topic (space-separated)</label>
		<input type="text" id="f-kt">
	</div>
	<div class="field">
		<label>Magnet Link</label>
		<textarea id="magnet-out" readonly rows="4"></textarea>
	</div>
	<button class="background-belize-hole color-clouds" id="copy-btn">Copy</button>
</section>

<script>
(function () {

	const ANNOUNCE = <?php echo json_encode($announce_url); ?>;

	// -------------------------------------------------------------------------
	// Bencode parser
	// Returns { torrent, infoBytes } where infoBytes is the raw ArrayBuffer
	// slice of the info value (needed for SHA-1).
	// -------------------------------------------------------------------------
	function parseTorrent(arrayBuffer) {
		const bytes = new Uint8Array(arrayBuffer);
		let pos = 0;

		function readDigits(stopCode) {
			let s = '';
			while (bytes[pos] !== stopCode) s += String.fromCharCode(bytes[pos++]);
			pos++; // skip stop byte
			return s;
		}

		function parseString() {
			const len = parseInt(readDigits(58), 10); // stop at ':'
			const slice = bytes.slice(pos, pos + len);
			pos += len;
			return slice; // Uint8Array
		}

		function parseValue() {
			const b = bytes[pos];
			if (b === 105) { // 'i'
				pos++;
				const n = parseInt(readDigits(101), 10); // stop at 'e'
				return n;
			}
			if (b === 108) { // 'l'
				pos++;
				const arr = [];
				while (bytes[pos] !== 101) arr.push(parseValue()); // 'e'
				pos++;
				return arr;
			}
			if (b === 100) { // 'd'
				pos++;
				const obj = {};
				while (bytes[pos] !== 101) { // 'e'
					const key = decode(parseString());
					obj[key] = parseValue();
				}
				pos++;
				return obj;
			}
			if (b >= 48 && b <= 57) return parseString(); // '0'-'9'
			throw new Error('Unexpected byte ' + b + ' at position ' + pos);
		}

		// Parse the top-level dict manually so we can record the info byte range.
		if (bytes[pos++] !== 100) throw new Error('Torrent file is not a bencode dict.'); // 'd'
		const torrent = {};
		let infoStart = -1, infoEnd = -1;
		while (bytes[pos] !== 101) { // 'e'
			const key = decode(parseString());
			if (key === 'info') infoStart = pos;
			torrent[key] = parseValue();
			if (key === 'info' && infoStart >= 0) infoEnd = pos;
		}

		if (infoStart < 0) throw new Error('No info dict found in torrent file.');
		return { torrent, infoBytes: arrayBuffer.slice(infoStart, infoEnd) };
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------
	function decode(bytes) {
		try { return new TextDecoder('utf-8').decode(bytes); }
		catch (e) { return ''; }
	}

	async function sha1hex(buffer) {
		if (!crypto || !crypto.subtle) throw new Error('SHA-1 requires a secure context (HTTPS or localhost).');
		const hash = await crypto.subtle.digest('SHA-1', buffer);
		return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
	}

	function extractInfo(torrent) {
		const info = torrent.info || {};

		// Name: prefer UTF-8 variant
		const nameBytes = info['name.utf-8'] || info['name'];
		const name = nameBytes instanceof Uint8Array ? decode(nameBytes) : '';

		// Size: single-file vs multi-file torrent
		let size = 0;
		if (typeof info.length === 'number') {
			size = info.length;
		} else if (Array.isArray(info.files)) {
			for (const f of info.files) {
				if (typeof f.length === 'number') size += f.length;
			}
		}

		// Trackers: deduplicated, our announce URL first
		const trackers = [ANNOUNCE];
		const add = (url) => { if (url && !trackers.includes(url)) trackers.push(url); };
		if (torrent.announce) add(decode(torrent.announce));
		if (Array.isArray(torrent['announce-list'])) {
			for (const tier of torrent['announce-list']) {
				if (Array.isArray(tier)) for (const u of tier) add(decode(u));
			}
		}

		// Web seeds — also used as exact sources for broader client compatibility
		const webSeeds = [];
		const exactSources = [];
		const addSeed = (u) => { const s = decode(u); if (s) { webSeeds.push(s); exactSources.push(s); } };
		if (torrent['url-list']) {
			const ul = torrent['url-list'];
			if (ul instanceof Uint8Array) addSeed(ul);
			else if (Array.isArray(ul)) for (const u of ul) addSeed(u);
		}

		return { name, size, trackers, webSeeds, exactSources };
	}

	function buildMagnet(hash, name, size, trackers, webSeeds, exactSources, acceptableSources, keywords) {
		let m = 'magnet:?xt=urn:btih:' + hash;
		if (name)     m += '&dn=' + encodeURIComponent(name);
		if (size)     m += '&xl=' + size;
		for (const tr of trackers)         if (tr.trim()) m += '&tr=' + encodeURIComponent(tr.trim());
		for (const ws of webSeeds)         if (ws.trim()) m += '&ws=' + encodeURIComponent(ws.trim());
		for (const xs of exactSources)     if (xs.trim()) m += '&xs=' + encodeURIComponent(xs.trim());
		for (const as of acceptableSources) if (as.trim()) m += '&as=' + encodeURIComponent(as.trim());
		if (keywords.trim()) m += '&kt=' + encodeURIComponent(keywords.trim());
		return m;
	}

	// -------------------------------------------------------------------------
	// UI
	// -------------------------------------------------------------------------
	const dropZone  = document.getElementById('drop-zone');
	const fileInput = document.getElementById('file-input');
	const errorEl   = document.getElementById('error');
	const results   = document.getElementById('results');
	const fXt       = document.getElementById('f-xt');
	const fDn       = document.getElementById('f-dn');
	const fXl       = document.getElementById('f-xl');
	const fTr       = document.getElementById('f-tr');
	const fWs       = document.getElementById('f-ws');
	const fXs       = document.getElementById('f-xs');
	const fAs       = document.getElementById('f-as');
	const fKt       = document.getElementById('f-kt');
	const magnetOut = document.getElementById('magnet-out');
	const copyBtn   = document.getElementById('copy-btn');

	function showError(msg) {
		errorEl.textContent = msg;
		errorEl.style.display = '';
		results.style.display = 'none';
	}

	function updateMagnet() {
		const hash             = fXt.value.trim();
		const name             = fDn.value.trim();
		const size             = parseInt(fXl.value, 10) || 0;
		const trackers         = fTr.value.split('\n');
		const webSeeds         = fWs.value.split('\n');
		const exactSources     = fXs.value.split('\n');
		const acceptableSources = fAs.value.split('\n');
		const keywords         = fKt.value;
		magnetOut.value = hash ? buildMagnet(hash, name, size, trackers, webSeeds, exactSources, acceptableSources, keywords) : '';
	}

	async function handleFile(file) {
		errorEl.style.display = 'none';
		if (!file || !file.name.endsWith('.torrent')) {
			showError('Please drop a .torrent file.');
			return;
		}
		try {
			const buffer = await file.arrayBuffer();
			const { torrent, infoBytes } = parseTorrent(buffer);
			const hash = await sha1hex(infoBytes);
			const { name, size, trackers, webSeeds, exactSources } = extractInfo(torrent);

			fXt.value = hash;
			fDn.value = name;
			fXl.value = size || '';
			fTr.value = trackers.join('\n');
			fWs.value = webSeeds.join('\n');
			fXs.value = exactSources.join('\n');

			updateMagnet();
			results.style.display = '';
		} catch (e) {
			showError(e.message);
		}
	}

	// Drag and drop
	dropZone.addEventListener('dragover',  (e) => { e.preventDefault(); dropZone.classList.add('over'); });
	dropZone.addEventListener('dragleave', ()  => { dropZone.classList.remove('over'); });
	dropZone.addEventListener('drop',      (e) => { e.preventDefault(); dropZone.classList.remove('over'); handleFile(e.dataTransfer.files[0]); });
	dropZone.addEventListener('click',     ()  => fileInput.click());
	fileInput.addEventListener('change',   ()  => handleFile(fileInput.files[0]));

	// Live magnet link update when fields are edited
	[fDn, fXl, fTr, fWs, fXs, fAs, fKt].forEach(el => el.addEventListener('input', updateMagnet));

	// Copy
	copyBtn.addEventListener('click', () => {
		navigator.clipboard.writeText(magnetOut.value).then(() => {
			copyBtn.textContent = 'Copied!';
			setTimeout(() => copyBtn.textContent = 'Copy', 1500);
		});
	});

})();
</script>

</body>
</html>
