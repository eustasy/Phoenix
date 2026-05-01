<?php

////	auth_render_login_form
// Render the login form HTML.
// Displays error message if $show_error is true.
// Calls exit() after rendering.

function auth_render_login_form($show_error = false) {
	?><!DOCTYPE html>
<html lang="en">
<head>
	<title>Phoenix &mdash; Login</title>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/combine/gh/eustasy/Colors.css@1/colors.min.css,gh/necolas/normalize.css@8/normalize.min.css">
	<style>
		body { margin: 0 auto; max-width: 400px; padding: 5% 10%; text-align: center; width: 80%; }
		h1 { font-weight: normal; }
		input[type="password"] { border: 1px solid #bdc3c7; border-radius: .2em; box-sizing: border-box; padding: .4em; width: 100%; }
		input[type="submit"] { border: none; }
		.box { padding: 1em; }
		.button { border-radius: .2em; padding: .4em .8em; }
		.field { margin: .8em 0; text-align: left; }
		.field label { color: #7f8c8d; display: block; font-size: .9em; }
	</style>
</head>
<body>
	<h1>Phoenix</h1>
	<?php if ( $show_error ): ?>
		<p class="box background-pomegranate color-clouds">Incorrect password.</p>
	<?php endif; ?>
	<form method="POST" action="">
		<input type="hidden" name="process" value="login">
		<div class="field"><label>Password</label>
			<input type="password" name="password" autofocus>
		</div>
		<input class="button background-belize-hole color-clouds" type="submit" value="Log In">
	</form>
</body>
</html>
<?php
	exit;
}
