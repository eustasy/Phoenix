<?php

declare(strict_types=1);

////	view_public_layout_html
// Page chrome for the public-facing surfaces (Torrent Index, Stats, Magnet):
// the flame-marked header with Index/Stats/Magnet nav + theme toggle, the
// centred main column (optionally a narrower "prose" measure), and the footer.
// The per-page controller builds $body (trusted HTML) and passes it in, so this
// view stays pure — no DB, no request state — which lets the un-bootstrapped
// magnet page reuse it too. $version is shown in the footer (omitted if empty,
// as on the standalone magnet page). Returns the full HTML document.
//
// Parameters:
//   $title      - page name; the <title> is "<title> — <wordmark>" (plain;
//                 escaped in view_head_html)
//   $body       - trusted HTML for the main column
//   $active     - 'index' | 'stats' | 'magnet'; highlights the matching nav link
//   $version    - phoenix_version for the footer, or '' to hide it
//   $prose      - narrow the main column for reading (Stats, Magnet)
//   $extra_head - per-page <style>/<link> injected into <head>
//   $inline_js  - inline JS for the rare page that must receive PHP data
//                 (emitted in a <script> only when non-empty)
//   $extra_srcs - per-page <script src> URLs (feature/page .js + libraries)
//   $brand      - the operator's branding, from the public_favicon, public_mark
//                 and public_wordmark settings: 'favicon' and 'mark' are image
//                 URLs, 'wordmark' is plain text (escaped here). Each one that
//                 is missing or empty keeps Phoenix's own.

/**
 * @param list<string> $extra_srcs
 * @param array{favicon?: string, mark?: string, wordmark?: string} $brand
 */
function view_public_layout_html(string $title, string $body, string $active, string $version = '', bool $prose = false, string $extra_head = '', string $inline_js = '', array $extra_srcs = [], array $brand = []): string
{
    require_once __DIR__.'/html.head.php';
    require_once __DIR__.'/html.mark.php';
    require_once __DIR__.'/html.theme.toggle.php';
    require_once __DIR__.'/html.scripts.php';

    ////	Navigation
    // Each link is the .php entry point so it resolves with or without the
    // optional extension-stripping rewrite rules.
    $nav_items = [
        'index' => ['index.php', 'Index'],
        'stats' => ['scrape.php?stats', 'Stats'],
        'magnet' => ['magnet.php', 'Magnet'],
    ];
    $nav_links = '';
    foreach ($nav_items as $key => [$href, $label]) {
        $cls = $key === $active ? ' class="is-active"' : '';
        $nav_links .= '<a href="'.$href.'"'.$cls.'>'.$label.'</a>';
    }

    $version_html = $version !== ''
        ? ' <span class="mono">'.htmlspecialchars($version, ENT_QUOTES, 'UTF-8').'</span>'
        : '';

    $main_class = 'ph-pub-main'.($prose ? ' prose' : '');

    ////	Branding
    // A custom mark is decorative: the wordmark beside it already names the link.
    // The wordmark also names the site in the page title.
    $mark = $brand['mark'] ?? '';
    $mark_html = $mark !== ''
        ? '<img src="'.htmlspecialchars($mark, ENT_QUOTES, 'UTF-8').'" alt="">'
        : view_mark_html();
    $wordmark = $brand['wordmark'] ?? '';
    $wordmark = $wordmark !== '' ? $wordmark : 'Phoenix';

    return view_head_html($title.' — '.$wordmark, $extra_head, '', $brand['favicon'] ?? '').'
<body>
<div class="ph-public">

	<header class="ph-pub-header">
		<div class="ph-pub-header-in">
			<a class="ph-pub-brand" href="index.php">
				'.$mark_html.'
				<span class="ph-wordmark">'.htmlspecialchars($wordmark, ENT_QUOTES, 'UTF-8').'</span>
			</a>
			<nav class="ph-pub-nav">
				'.$nav_links.'
				'.view_theme_toggle_html('Light', 'Dark').'
			</nav>
		</div>
	</header>

	<main class="'.$main_class.'">
		'.$body.'
	</main>

	<footer class="ph-pub-footer">
		<div class="ph-pub-footer-in">
			<span>Phoenix'.$version_html.' &middot; a lightweight BitTorrent tracker</span>
			<span><a href="index.php">Index</a> &middot; <a href="scrape.php?stats">Stats</a> &middot; <a href="magnet.php">Magnet</a></span>
		</div>
	</footer>

</div>
'.view_scripts_html($inline_js, $extra_srcs).'
</body>
</html>';
}
