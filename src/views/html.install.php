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
//   $totp_secret - string|null, candidate base32 secret; null hides the whole 2FA section (library absent)
//   $totp_qr - string|null, base64-encoded PNG QR (no data: prefix), or null when GD is unavailable
//   $totp_url - string|null, otpauth:// URL for manual entry

/**
 * @param array{db_host: string, db_user: string, db_name: string, db_prefix: string, db_persist: bool, open_tracker: bool, public_index: bool} $form
 */
function view_install_html(
    bool $settings_writable,
    string|null $install_error,
    array $form,
    string|null $totp_secret = null,
    string|null $totp_qr = null,
    string|null $totp_url = null,
): string {
    $error_html = '';
    if ($install_error) {
        $error_html = '<p class="box background-pomegranate color-clouds">'.htmlspecialchars($install_error).'</p>';
    }

    ////	Optional two-factor section
    // Rendered only when the controller passes a secret (i.e. the verification
    // library is installed). Shows the QR when GD produced one, otherwise the
    // secret + otpauth URL for manual entry. The hidden totp_secret field
    // round-trips the displayed secret so a failed code re-renders the same one.
    $totp_html = '';
    if ($totp_secret !== null) {
        if ($totp_qr !== null && $totp_qr !== '') {
            $totp_display = '<p><img src="data:image/png;base64,'.htmlspecialchars($totp_qr).'" alt="Two-factor QR code"></p>';
        } else {
            $totp_display = '<p>Scan is unavailable (no image support). Add this secret manually:</p>
				<p><code>'.htmlspecialchars($totp_secret).'</code></p>';
            if ($totp_url !== null) {
                $totp_display .= '<p><a href="'.htmlspecialchars($totp_url).'">'.htmlspecialchars($totp_url).'</a></p>';
            }
        }

        $totp_html = '
				<h2>(Optional) Two-Factor Authentication</h2>
				<p>Scan the code with an authenticator app, then enter a code to enable &mdash; or leave it blank to skip.</p>
				'.$totp_display.'
				<input type="hidden" name="totp_secret" value="'.htmlspecialchars($totp_secret).'">
				<div class="field"><label>Authentication Code</label>
					<input type="text" name="totp_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6">
				</div>';
    }

    $writable_warning = '';
    $form_html = '';
    if (! $settings_writable) {
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
			<div class="field"><label>Admin Password (required)</label>
				<input type="password" name="admin_password" required>
			</div>
			'.$totp_html.'
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
