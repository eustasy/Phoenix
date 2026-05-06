<?php

declare(strict_types=1);

////	view_install_html
// Render the installation form.
// Returns HTML string. Caller is responsible for echo and exit.
//
// Parameters:
//   $settings_writable - bool, whether config/ directory is writable
//   $install_error - string|false, error message to display
//   $form - array, form field values (db_host, db_user, db_name, db_prefix, db_persist, open_tracker, public_index)

function view_install_html($settings_writable, $install_error, $form): string {
	$error_html = '';
	if ( $install_error ) {
		$error_html = '<p class="box background-pomegranate color-clouds">'.htmlspecialchars($install_error).'</p>';
	}
	
	$writable_warning = '';
	$form_html = '';
	if ( !$settings_writable ) {
		$writable_warning = '<p class="box background-pomegranate color-clouds">
			<code>config/</code> is not writable. Make it writable to proceed with installation.
		</p>';
	} else {
		$form_html = $error_html.'
		<form method="POST" action="">
			<input type="hidden" name="process" value="install">
			<h2>Database</h2>
			<div class="field"><label>Host</label>
				<input type="text" name="db_host" value="'.htmlspecialchars($form['db_host']).'">
			</div>
			<div class="field"><label>Username</label>
				<input type="text" name="db_user" value="'.htmlspecialchars($form['db_user']).'">
			</div>
			<div class="field"><label>Password</label>
				<input type="password" name="db_pass">
			</div>
			<div class="field"><label>Database Name</label>
				<input type="text" name="db_name" value="'.htmlspecialchars($form['db_name']).'">
			</div>
			<div class="field"><label>Table Prefix</label>
				<input type="text" name="db_prefix" value="'.htmlspecialchars($form['db_prefix']).'">
			</div>
			<div class="field checkbox">
				<label><input type="checkbox" name="db_persist" value="1"'.($form['db_persist'] ? ' checked' : '').'>
				Persistent Connections</label>
			</div>
			<h2>Tracker</h2>
			<div class="field checkbox">
				<label><input type="checkbox" name="open_tracker" value="1"'.($form['open_tracker'] ? ' checked' : '').'>
				Open Tracker &mdash; track any announced torrent</label>
			</div>
			<div class="field checkbox">
				<label><input type="checkbox" name="public_index" value="1"'.($form['public_index'] ? ' checked' : '').'>
				Public Index &mdash; list torrents on the index page</label>
			</div>
			<h2>Admin</h2>
			<div class="field"><label>Admin Password (leave blank for no authentication)</label>
				<input type="password" name="admin_password">
			</div>
			<br>
			<input class="button background-belize-hole color-clouds" type="submit" value="Install">
		</form>';
	}
	
	return '<!DOCTYPE html>
<html lang="en">
<head>
	<title>Phoenix Setup</title>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/normalize.css@8.0.1/normalize.css" integrity="sha256-WAgYcAck1C1/zEl5sBl5cfyhxtLgKGdpI3oKyJffVRI=" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/eustasy/colors.css@2.0.9/flatui.min.css" integrity="sha256-88LCIpF5risV+CCY/1CbWvHUJ7Rxg5KIj1tTg4ZUZLQ=" crossorigin="anonymous">
	<style>
		body { margin: 0 auto; max-width: 600px; padding: 1% 10%; text-align: center; width: 80%; }
		h1, h2, h3, h4, h5, h6 { font-weight: normal; }
		a { text-decoration: none; }
		input[type="text"],
		input[type="password"] { border: 1px solid #bdc3c7; border-radius: .2em; box-sizing: border-box; padding: .3em; width: 100%; }
		input[type="submit"] { border: none; }
		input:disabled { background: #ecf0f1; color: #7f8c8d; }
		.box { padding: 1em; }
		.button { border-radius: .2em; padding: .3em; }
		.field { margin: .5em 0; text-align: left; }
		.field label { color: #7f8c8d; display: block; font-size: .9em; }
		.field.checkbox label { display: inline; font-size: 1em; }
	</style>
</head>
<body>
	<h1>Phoenix Setup</h1>
	'.$writable_warning.$form_html.'
</body>
</html>';
}
