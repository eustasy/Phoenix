<?php

declare(strict_types=1);

////	view_auth_layout_html
// Centred page chrome for the unauthenticated operator surfaces (Login and the
// first-run Installer): a fixed theme toggle, the flame brand block with a
// heading + subtitle, the caller's $body (the form card), and an optional
// footer line. The per-page controller builds $body (trusted HTML); this view
// stays pure (no DB, no request state) so the installer can use it before
// phoenix.php is bootstrapped. Returns the full HTML document.
//
// Parameters:
//   $title      - <title> text (plain; escaped here)
//   $heading    - brand heading (plain; escaped)
//   $subtitle   - brand subtitle (plain; escaped)
//   $body       - trusted HTML for the card area
//   $foot       - trusted HTML for the footer line, or '' to omit it
//   $wide       - widen the card (the multi-fieldset installer opts in)
//   $wrap_class - extra CSS class(es) for the .ph-auth wrapper (e.g.
//                 ph-auth-install for the installer's top-aligned variant)
//   $extra_head - per-page <style>/<link> injected into <head>
//   $inline_js  - per-page inline JS appended after assets/app.js

function view_auth_layout_html(string $title, string $heading, string $subtitle, string $body, string $foot = '', bool $wide = false, string $wrap_class = '', string $extra_head = '', string $inline_js = ''): string
{
    require_once __DIR__.'/html.head.php';
    require_once __DIR__.'/html.mark.php';
    require_once __DIR__.'/html.theme.toggle.php';
    require_once __DIR__.'/html.scripts.php';

    $card_class = 'ph-auth-card'.($wide ? ' ph-auth-wide' : '');
    $wrap_classes = 'ph-auth'.($wrap_class !== '' ? ' '.$wrap_class : '');
    $foot_html = $foot !== '' ? '
		<p class="ph-auth-foot">'.$foot.'</p>' : '';

    return view_head_html($title, $extra_head).'
<body>
<div class="'.$wrap_classes.'">
	'.view_theme_toggle_html('Light', 'Dark', 'ph-theme-fixed').'

	<div class="'.$card_class.'">
		<div class="ph-auth-brand">
			'.view_mark_html().'
			<div>
				<h1>'.htmlspecialchars($heading, ENT_QUOTES, 'UTF-8').'</h1>
				<p>'.htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8').'</p>
			</div>
		</div>
		'.$body.$foot_html.'
	</div>
</div>
'.view_scripts_html($inline_js).'
</body>
</html>';
}
