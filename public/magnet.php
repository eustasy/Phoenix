<?php

declare(strict_types=1);

////	Magnet Generator
// A self-contained, client-side magnet builder. Deliberately does NOT bootstrap
// src/phoenix.php — it never touches the tracker or database, so it works even
// when the tracker is misconfigured. It reuses only the pure view partials for
// the shared page chrome.

// magnet.php does not bootstrap phoenix.php, so it loads the security-header
// helper itself. It is a browser-facing HTML page → the public-HTML profile.
require_once __DIR__.'/../src/functions/http.security.headers.php';
http_security_headers('public_html');

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

// Static page body — self-contained markup in src/partials/magnet.body.html so
// it can be HTML-/accessibility-linted. Captured into $body for the layout.
ob_start();
include __DIR__.'/../src/partials/magnet.body.html';
$body = (string) ob_get_clean();

// The magnet logic lives in assets/_magnet.js; parsing is delegated to
// assets/torrent-parse.js (PhoenixTorrent), loaded as a normal source. _magnet.js
// is read in and emitted inline (prefixed with the PHP-computed ANNOUNCE URL) so
// that value is in scope — hence the "_" name marking it an inlined file.
$inline_js = 'const ANNOUNCE = '.json_encode($announce_url).";\n"
    .(string) file_get_contents(__DIR__.'/assets/_magnet.js');

echo view_public_layout_html('Magnet Generator — Phoenix', $body, 'magnet', '', true, $extra_head, $inline_js, ['/assets/torrent-parse.js']);
