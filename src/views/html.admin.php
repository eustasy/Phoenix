<?php

declare(strict_types=1);

////	view_admin_html
// Render the admin panel with diagnostics and utilities.
// Returns HTML string. Caller is responsible for echo and exit.
//
// Parameters:
//   $settings - settings array
//   $tables_installed - bool, whether all tables are installed
//   $database_size - array|false, database size info (Data, Indexes, Total, Free)
//   $message - string|false, optional message to display
//   $show_installed - bool, whether to show "Installation complete" message
//   $csrf_token - string, per-session token embedded in every form (empty when
//                 no admin_password is set, since CSRF is not enforced then).
//   $php_version - string|null, PHP version to report (defaults to PHP_VERSION).
//                  Override only used by tests so the unsupported-version
//                  branch can be exercised without spawning a different PHP.
//   $has_mysqli - bool|null, whether mysqli is available (defaults to
//                 class_exists('mysqli')). Override only used by tests so
//                 the missing-extension branch can be exercised.

/**
 * @param PhoenixSettings $settings
 * @param array<string, float|int|string|null>|false $database_size
 */
function view_admin_html(array $settings, bool $tables_installed, array|false $database_size, string|false $message = false, bool $show_installed = false, string $csrf_token = '', ?string $php_version = null, ?bool $has_mysqli = null): string
{
    // Hidden field carrying the CSRF token, embedded in every state-changing
    // form. Escaped defensively even though the token is always hex.
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    // Composer enforces ^8.2 and ext-mysqli, but the project supports manual
    // installs that bypass composer, so the runtime checks below stay in
    // place; tests pass overrides to reach the failure branches.
    $php_version ??= PHP_VERSION;
    $has_mysqli ??= class_exists('mysqli');

    // Build logout form. POST-only so a cross-site GET (e.g. an <img> tag)
    // cannot end an admin session.
    $logout_html = '';
    if (! empty($settings['admin_password'])) {
        $logout_html = '<form method="POST" class="text-right" style="display:inline">'.
            '<input type="hidden" name="logout" value="1">'.$csrf_field.
            '<button type="submit" class="link-button" style="background:none;border:none;padding:0;color:inherit;cursor:pointer;text-decoration:underline">Log out</button>'.
            '</form>';
    }

    // Build installation complete message
    $installed_html = '';
    if ($show_installed) {
        $installed_html = '<p class="box background-green-sea color-clouds">Installation complete.</p>';
    }

    // PHP version check
    if (version_compare($php_version, '8.2.0', '>=')) {
        $php_compat_html = '<p class="box background-green-sea color-clouds">Your PHP version is supported.</p>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
    } else {
        $php_compat_html = '<p class="box background-pomegranate color-clouds">Phoenix requires PHP &gt;= 8.2.</p>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
    }

    // MySQL support check
    $mysql_html = '';
    if (! $has_mysqli) {
        $mysql_html = '<p class="box background-pomegranate color-clouds">Your server does not support MySQL.</p>';
    } else {
        // mysqli_get_client_info typically returns "mysqlnd 8.x.y-…", but a
        // build without a '-' suffix is valid; strpos() returns false there
        // and substr() under strict_types refuses a false length.
        $mysql_version = mysqli_get_client_info();
        $dash = strpos($mysql_version, '-');
        $mysql_version = trim(
            $dash !== false ? substr($mysql_version, 0, $dash) : $mysql_version,
            'mysqlnd ',
        );
        $mysql_html = '<p class="box background-green-sea color-clouds">Your server supports MySQL.</p>
		<p class="color-asbestos">MySQL Version: '.$mysql_version.'</p>';

        // Tables status
        if ($tables_installed) {
            $mysql_html .= '<p class="box background-green-sea color-clouds">All your tables are installed.';
            if ($database_size) {
                $mysql_html .= ' Their current size is '.number_format((float) ($database_size['Total'] ?? 0)).' bytes.';
            }
            $mysql_html .= '</p>';
        } else {
            $mysql_html .= '<p class="box background-pomegranate color-clouds">Some or all of your tables are not installed.</p>';
        }

        // Utilities section
        $mysql_html .= '<br><h1>Utilities</h1>';

        // Message
        if ($message) {
            $mysql_html .= '<div class="box background-wisteria color-clouds"><h3>'.htmlspecialchars($message).'</h3></div>';
        }

        // Setup/Reset form
        if ($settings['db_reset'] || ! $tables_installed) {
            $mysql_html .= '<form class="mysql" action="" method="POST">
				<p class="box background-pomegranate color-clouds">You should set
				<code>$settings[\'db_reset\']</code>
				to false to disable resets,<br>
				or delete <code>public/admin.php</code> when you\'re up and running.</p>
				<p class="float-left text-left">Install, Upgrade, and Reset</p>
				<input type="hidden" name="process" value="setup">'.$csrf_field.'
				<input class="button background-belize-hole color-clouds float-right" type="submit" name="submit" value="Setup">
				<div class="clear"></div>
			</form>';
        } else {
            $mysql_html .= '<p class="text-left color-asbestos">Install, Upgrade, and Reset
				<span class="button background-clouds float-right">Disabled</span></p>
				<div class="clear"></div>';
        }

        // Clean and Optimize forms (only if tables are installed)
        if ($tables_installed) {
            $mysql_html .= '<form class="mysql" action="" method="POST">
					<p class="float-left text-left">Clean out redundant peers</p>
					<input type="hidden" name="process" value="clean">'.$csrf_field.'
					<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Clean">
					<div class="clear"></div>
				</form>';
            $mysql_html .= '<form class="mysql" action="" method="POST">
					<p class="float-left text-left">Check, Analyze, Repair, and Optimize</p>
					<input type="hidden" name="process" value="optimize">'.$csrf_field.'
					<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Optimize">
					<div class="clear"></div>
				</form>';
        }
    }

    return '<!DOCTYPE html>
<html lang="en">
<head>
	<title>Phoenix Diagnostics and Utilities</title>
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
	</style>
</head>
<body>
	<h1>Compatibility Check</h1>
	<p class="text-center color-9">'.$settings['phoenix_version'].'</p>
	'.$logout_html.'
	'.$installed_html.'
	'.$php_compat_html.'
	'.$mysql_html.'
</body>
</html>';
}
