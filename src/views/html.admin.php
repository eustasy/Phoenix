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

function view_admin_html($settings, $tables_installed, $database_size, $message = false, $show_installed = false): string {
	$php_version = PHP_VERSION;
	
	// Build logout form. POST-only so a cross-site GET (e.g. an <img> tag)
	// cannot end an admin session.
	$logout_html = '';
	if ( !empty($settings['admin_password']) ) {
		$logout_html = '<form method="POST" class="text-right" style="display:inline">'.
			'<input type="hidden" name="logout" value="1">'.
			'<button type="submit" class="link-button" style="background:none;border:none;padding:0;color:inherit;cursor:pointer;text-decoration:underline">Log out</button>'.
			'</form>';
	}
	
	// Build installation complete message
	$installed_html = '';
	if ( $show_installed ) {
		$installed_html = '<p class="box background-green-sea color-clouds">Installation complete.</p>';
	}
	
	// PHP version check. Composer already enforces ^8.2 at install time, so
	// reaching this view at all guarantees we're on a supported version; the
	// panel just surfaces the running version for diagnostics.
	if ( version_compare(PHP_VERSION, '8.2.0', '>=') ) {
		$php_compat_html = '<p class="box background-green-sea color-clouds">Your PHP version is supported.</p>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
	} else {
		$php_compat_html = '<p class="box background-pomegranate color-clouds">Phoenix requires PHP &gt;= 8.2.</p>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
	}
	
	// MySQL support check
	$mysql_html = '';
	if ( !class_exists('mysqli') ) {
		$mysql_html = '<p class="box background-pomegranate color-clouds">Your server does not support MySQL.</p>';
	} else {
		// mysqli_get_client_info typically returns "mysqlnd 8.x.y-…", but a
		// build without a '-' suffix is valid; strpos() returns false there
		// and substr() under strict_types refuses a false length.
		$mysql_version = mysqli_get_client_info();
		$dash = strpos($mysql_version, '-');
		$mysql_version = trim(
			$dash !== false ? substr($mysql_version, 0, $dash) : $mysql_version,
			'mysqlnd '
		);
		$mysql_html = '<p class="box background-green-sea color-clouds">Your server supports MySQL.</p>
		<p class="color-asbestos">MySQL Version: '.$mysql_version.'</p>';
		
		// Tables status
		if ( $tables_installed ) {
			$mysql_html .= '<p class="box background-green-sea color-clouds">All your tables are installed.';
			if ( $database_size ) {
				$mysql_html .= ' Their current size is '.number_format($database_size['Total']).' bytes.';
			}
			$mysql_html .= '</p>';
		} else {
			$mysql_html .= '<p class="box background-pomegranate color-clouds">Some or all of your tables are not installed.</p>';
		}
		
		// Utilities section
		$mysql_html .= '<br><h1>Utilities</h1>';
		
		// Message
		if ( $message ) {
			$mysql_html .= '<div class="box background-wisteria color-clouds"><h3>'.htmlspecialchars($message).'</h3></div>';
		}
		
		// Setup/Reset form
		if ( $settings['db_reset'] || !$tables_installed ) {
			$mysql_html .= '<form class="mysql" action="" method="POST">
				<p class="box background-pomegranate color-clouds">You should set
				<code>$settings[\'db_reset\']</code>
				to false to disable resets,<br>
				or delete <code>public/admin.php</code> when you\'re up and running.</p>
				<p class="float-left text-left">Install, Upgrade, and Reset</p>
				<input type="hidden" name="process" value="setup">
				<input class="button background-belize-hole color-clouds float-right" type="submit" name="submit" value="Setup">
				<div class="clear"></div>
			</form>';
		} else {
			$mysql_html .= '<p class="text-left color-asbestos">Install, Upgrade, and Reset
				<span class="button background-clouds float-right">Disabled</span></p>
				<div class="clear"></div>';
		}
		
		// Clean and Optimize forms (only if tables are installed)
		if ( $tables_installed ) {
			$mysql_html .= '<form class="mysql" action="" method="POST">
					<p class="float-left text-left">Clean out redundant peers</p>
					<input type="hidden" name="process" value="clean">
					<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Clean">
					<div class="clear"></div>
				</form>';
			$mysql_html .= '<form class="mysql" action="" method="POST">
					<p class="float-left text-left">Check, Analyze, Repair, and Optimize</p>
					<input type="hidden" name="process" value="optimize">
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
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/combine/gh/eustasy/Colors.css@1/colors.min.css,gh/necolas/normalize.css@8/normalize.min.css">
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
