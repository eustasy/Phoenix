<?php

declare(strict_types=1);

////	view_login_html
// Render the login form HTML.
// Displays error message if $show_error is true.
// Renders a TOTP code field when $totp_required is true.
// Returns HTML string. Caller is responsible for echo and exit.

function view_login_html(bool $show_error = false, bool $totp_required = false): string
{
    $error_html = '';
    if ($show_error) {
        $error_html = '<p class="box background-pomegranate color-clouds">Incorrect password.</p>';
    }

    ////	Optional second-factor field
    $code_html = '';
    if ($totp_required) {
        $code_html = '<div class="field"><label>Authentication Code</label>
				<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6">
			</div>';
    }

    return '<!DOCTYPE html>
<html lang="en">
<head>
	<title>Phoenix &mdash; Login</title>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/normalize.css@8.0.1/normalize.css" integrity="sha256-WAgYcAck1C1/zEl5sBl5cfyhxtLgKGdpI3oKyJffVRI=" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/eustasy/colors.css@2.0.9/flatui.min.css" integrity="sha256-88LCIpF5risV+CCY/1CbWvHUJ7Rxg5KIj1tTg4ZUZLQ=" crossorigin="anonymous">
	<style>
		body { margin: 0 auto; max-width: 400px; padding: 5% 10%; text-align: center; width: 80%; }
		h1 { font-weight: normal; }
		input[type="password"],
		input[type="text"] { border: 1px solid #bdc3c7; border-radius: .2em; box-sizing: border-box; padding: .4em; width: 100%; }
		input[type="submit"] { border: none; }
		.box { padding: 1em; }
		.button { border-radius: .2em; padding: .4em .8em; }
		.field { margin: .8em 0; text-align: left; }
		.field label { color: #7f8c8d; display: block; font-size: .9em; }
	</style>
</head>
<body>
	<h1>Phoenix</h1>
	'.$error_html.'
	<form method="POST" action="">
		<input type="hidden" name="process" value="login">
		<div class="field"><label>Password</label>
			<input type="password" name="password" autofocus>
		</div>
		'.$code_html.'
		<input class="button background-belize-hole color-clouds" type="submit" value="Log In">
	</form>
</body>
</html>';
}
