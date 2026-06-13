<?php

declare(strict_types=1);

////	view_admin_layout_html
// Render the admin panel page chrome around a page-specific body.
// Owns the full HTML document: <head> (title, meta, double-submit guard,
// stylesheets, base styles), the version line, the logout form, and the
// admin navigation bar. The per-page controller assembles $body (trusted
// HTML) and passes it in, so this view stays pure: no auth, no session, no
// side effects. The CSRF token is passed in for the logout form rather than
// read here, keeping the view free of request state.
// Returns HTML string. Caller is responsible for echo and exit.
//
// Parameters:
//   $settings    - settings array (uses phoenix_version, admin_password)
//   $title       - page title, rendered as "Phoenix Admin: <title>"
//   $body        - trusted HTML body for the active page (not escaped)
//   $active      - page key of the current page; the matching nav link is
//                  marked current (aria-current + .current class)
//   $csrf_token  - per-session token for the logout form (empty when no
//                  admin_password is set, since CSRF is not enforced then)
//   $wide        - when true, widen the page column. Data pages (Torrents,
//                  Backups) opt in for tables; the diagnostics dashboard stays
//                  in the default narrow column.

/**
 * @param PhoenixSettings $settings
 */
function view_admin_layout_html(array $settings, string $title, string $body, string $active, string $csrf_token = '', bool $wide = false): string
{
    // Hidden field carrying the CSRF token for the logout form. Escaped
    // defensively even though the token is always hex.
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    // Build logout form. POST-only so a cross-site GET (e.g. an <img> tag)
    // cannot end an admin session. Only shown when auth is configured.
    $logout_html = '';
    if (! empty($settings['admin_password'])) {
        $logout_html = '<form method="POST" class="text-right" style="display:inline">'.
            '<input type="hidden" name="logout" value="1">'.$csrf_field.
            '<button type="submit" class="link-button" style="background:none;border:none;padding:0;color:inherit;cursor:pointer;text-decoration:underline">Log out</button>'.
            '</form>';
    }

    ////	Navigation
    // Four top-level admin pages. The link whose key matches $active is
    // marked current so the user (and tests) can tell where they are.
    $nav_items = [
        'dashboard' => 'Dashboard',
        'torrents' => 'Torrents',
        'backups' => 'Backups',
        'settings' => 'Settings',
    ];
    $nav_links = '';
    foreach ($nav_items as $key => $label) {
        if ($key === $active) {
            $nav_links .= '<a href="?page='.$key.'" class="nav-link current" aria-current="page">'.$label.'</a>';
        } else {
            $nav_links .= '<a href="?page='.$key.'" class="nav-link">'.$label.'</a>';
        }
    }
    $nav_html = '<nav class="admin-nav">'.$nav_links.'</nav>';

    return '<!DOCTYPE html>
<html lang="en">
<head>
	<title>Phoenix Admin: '.$title.'</title>
	<meta charset="UTF-8">
	<script>
		// Disable every submit button on the page when any .mysql form is
		// submitted, to prevent double-submission across the mutually
		// exclusive setup/clean/optimize forms.
		document.addEventListener(\'DOMContentLoaded\', function () {
			document.querySelectorAll(\'form.mysql\').forEach(function (form) {
				form.addEventListener(\'submit\', function () {
					document.querySelectorAll(\'input[type="submit"]\').forEach(function (btn) {
						btn.disabled = true;
					});
				});
			});
		});
	</script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/normalize.css@8.0.1/normalize.css" integrity="sha256-WAgYcAck1C1/zEl5sBl5cfyhxtLgKGdpI3oKyJffVRI=" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/eustasy/colors.css@2.0.9/flatui.min.css" integrity="sha256-88LCIpF5risV+CCY/1CbWvHUJ7Rxg5KIj1tTg4ZUZLQ=" crossorigin="anonymous">
	<style>
		body {
			margin: 0 auto;
			max-width: 600px;
			padding: 1% 10%;
			text-align: center;
			width: 80%;
		}
		h1,
		h2,
		h3,
		h4,
		h5,
		h6 {
			font-weight: normal;
		}
		a {
			text-decoration: none;
		}
		input {
			border: none;
		}
		input:disabled {
			background: #ecf0f1;
			color: #7f8c8d;
		}
		.box {
			padding: 1em;
		}
		.button {
			border-radius: .2em;
			padding: .3em;
		}
		p .button {
			margin-top: -.3em;
		}
		.button.p-like {
			margin: 0.7em 0;
		}
		.clear {
			clear: both;
		}
		.float-left {
			float: left;
		}
		.float-right {
			float: right;
		}
		.text-center {
			text-align: center;
		}
		.text-left {
			text-align: left;
		}
		.text-right {
			text-align: right;
		}
		.admin-nav {
			margin: 1em 0;
		}
		.admin-nav .nav-link {
			margin: 0 .5em;
		}
		.admin-nav .nav-link.current {
			font-weight: bold;
			text-decoration: underline;
		}
		body.wide {
			max-width: 1100px;
		}
		table.data-table {
			border-collapse: collapse;
			margin: 1em auto;
			text-align: left;
			width: 100%;
		}
		table.data-table th,
		table.data-table td {
			border-bottom: 1px solid #ecf0f1;
			padding: .4em .6em;
			vertical-align: top;
		}
		table.data-table code {
			word-break: break-all;
		}
	</style>
</head>
<body'.($wide ? ' class="wide"' : '').'>
	<p class="text-center color-9">'.$settings['phoenix_version'].'</p>
	'.$logout_html.'
	'.$nav_html.'
	'.$body.'
</body>
</html>';
}
