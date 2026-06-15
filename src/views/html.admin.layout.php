<?php

declare(strict_types=1);

////	view_admin_layout_html
// Render the admin panel chrome around a page-specific body: the sticky flame-
// marked sidebar (Tracker + Server nav groups, theme toggle, logout) and the
// main column's top bar (crumb + title + optional actions). The per-page
// controller assembles $body (trusted HTML) and passes it in, so this view
// stays pure — no auth, no session, no side effects. The CSRF token rides in
// for the logout form. Composed from the shared head/mark/theme-toggle/scripts
// partials. Returns the full HTML document; caller echoes and exits.
//
// Parameters:
//   $settings    - settings array (uses phoenix_version, admin_password)
//   $title       - page title; topbar <h1> and "Phoenix Admin: <title>"
//   $body        - trusted HTML body for the active page (.ph-body content)
//   $active      - nav key of the current page; that link is marked current
//   $csrf_token  - per-session token for the logout form (empty when no
//                  admin_password is set, since CSRF is not enforced then)
//   $crumb       - small label above the title (e.g. "Tracker" / "Server")
//   $actions     - trusted HTML for the topbar action area (buttons), or ''
//   $narrow      - narrow the body column (forms/diagnostics opt in)
//   $extra_head  - per-page <style>/<link> injected into <head>
//   $inline_js   - per-page inline JS appended after assets/app.js
//   $extra_srcs  - per-page <script src> URLs (e.g. the map library)

/**
 * @param PhoenixSettings $settings
 * @param list<string> $extra_srcs
 */
function view_admin_layout_html(array $settings, string $title, string $body, string $active, string $csrf_token = '', string $crumb = 'Tracker', string $actions = '', bool $narrow = false, string $extra_head = '', string $inline_js = '', array $extra_srcs = []): string
{
    require_once __DIR__.'/html.head.php';
    require_once __DIR__.'/html.mark.php';
    require_once __DIR__.'/html.theme.toggle.php';
    require_once __DIR__.'/html.scripts.php';

    ////	Navigation
    // Two groups, matching the Tracker / Server split. The link whose key is
    // $active is marked current (is-active + aria-current) so users and tests
    // can tell where they are.
    $groups = [
        'Tracker' => [
            'dashboard' => ['layout-dashboard', 'Dashboard'],
            'torrents' => ['database', 'Torrents'],
            'peers' => ['users', 'Peers'],
            'geography' => ['globe-2', 'Geography'],
            'add' => ['plus', 'Add Torrent'],
        ],
        'Server' => [
            'support' => ['server', 'Server Support'],
            'utilities' => ['wrench', 'Utilities'],
            'backups' => ['archive', 'Backups'],
            'settings' => ['settings', 'Settings'],
        ],
    ];
    $nav_html = '';
    foreach ($groups as $label => $items) {
        $links = '';
        foreach ($items as $key => [$icon, $text]) {
            $attrs = $key === $active ? ' class="is-active" aria-current="page"' : '';
            $links .= '<a href="?page='.$key.'"'.$attrs.'><span class="ph-ico" data-lucide="'.$icon.'"></span>'.$text.'</a>';
        }
        $nav_html .= '<div class="ph-navlabel">'.$label.'</div><nav class="ph-nav">'.$links.'</nav>';
    }

    ////	Logout — POST only (a cross-site GET cannot end the session) and only
    // when auth is configured.
    $logout_html = '';
    if (! empty($settings['admin_password'])) {
        $logout_html = '<form method="POST" style="display:inline">'.
            '<input type="hidden" name="logout" value="1">'.
            '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">'.
            '<button type="submit" class="btn btn-ghost btn-sm"><span class="ph-ico" data-lucide="log-out"></span>Log out</button>'.
            '</form>';
    }

    $crumb_html = $crumb !== '' ? '<p class="ph-crumb">'.htmlspecialchars($crumb, ENT_QUOTES, 'UTF-8').'</p>' : '';
    $actions_html = $actions !== '' ? '<div class="ph-topbar-actions">'.$actions.'</div>' : '';
    $body_class = 'ph-body'.($narrow ? ' narrow' : '');

    // Disable submit controls once any .mysql form is submitted, to prevent
    // double-submission across the mutually exclusive setup/clean/optimize forms.
    $guard_js = 'document.querySelectorAll("form.mysql").forEach(function(f){f.addEventListener("submit",function(){document.querySelectorAll(\'button[type="submit"],input[type="submit"]\').forEach(function(b){b.disabled=true;});});});';

    return view_head_html('Phoenix Admin: '.$title, $extra_head).'
<body>
<div class="app">

	<aside class="ph-sidebar">
		<a class="ph-brand" href="admin.php">
			'.view_mark_html().'
			<div>
				<div class="ph-wordmark">Phoenix</div>
				<div class="ph-ver">'.htmlspecialchars($settings['phoenix_version'], ENT_QUOTES, 'UTF-8').'</div>
			</div>
		</a>

		'.$nav_html.'

		<div class="ph-sidebar-foot">
			'.view_theme_toggle_html('Light mode', 'Dark mode').'
			<hr class="ph-sidebar-sep">
			<div class="flex items-center" style="justify-content:space-between;gap:var(--space-2)">
				<span class="dim" style="font-size:var(--font-size-xs);font-family:var(--font-mono)">eustasy</span>
				'.$logout_html.'
			</div>
		</div>
	</aside>

	<main class="ph-main">
		<header class="ph-topbar">
			<div>
				'.$crumb_html.'
				<h1>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</h1>
			</div>
			'.$actions_html.'
		</header>
		<div class="'.$body_class.'">
			'.$body.'
		</div>
	</main>

</div>
'.view_scripts_html($guard_js."\n".$inline_js, $extra_srcs).'
</body>
</html>';
}
