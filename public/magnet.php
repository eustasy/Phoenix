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

	<div class="alert alert-danger alert-center" id="error" hidden><span class="ph-ico" data-lucide="circle-alert"></span><span id="error-text"></span></div>

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

			<div class="ph-field mb-0">
				<label>Magnet Link</label>
				<div class="magnet-output-wrap">
					<textarea id="magnet-out" class="magnet-out" readonly rows="4"></textarea>
					<button class="btn btn-primary btn-sm copy-float" id="copy-btn"><span class="ph-ico" data-lucide="copy"></span>Copy</button>
				</div>
			</div>
		</div>
	</section>';

// The magnet logic lives in assets/_magnet.js; parsing is delegated to
// assets/torrent-parse.js (PhoenixTorrent), loaded as a normal source. _magnet.js
// is read in and emitted inline (prefixed with the PHP-computed ANNOUNCE URL) so
// that value is in scope — hence the "_" name marking it an inlined file.
$inline_js = 'const ANNOUNCE = '.json_encode($announce_url).";\n"
    .(string) file_get_contents(__DIR__.'/assets/_magnet.js');

echo view_public_layout_html('Magnet Generator — Phoenix', $body, 'magnet', '', true, $extra_head, $inline_js, ['/assets/torrent-parse.js']);
