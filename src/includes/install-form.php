<?php

// Repopulate form values after a failed attempt.
$f = array(
	'db_host'      => isset($db_host)      ? htmlspecialchars($db_host)   : 'localhost',
	'db_user'      => isset($db_user)       ? htmlspecialchars($db_user)   : '',
	'db_name'      => isset($db_name)       ? htmlspecialchars($db_name)   : 'phoenix',
	'db_prefix'    => isset($db_prefix)     ? htmlspecialchars($db_prefix) : 'phoenix_',
	'db_persist'   => !isset($db_persist)   || $db_persist,
	'open_tracker' => isset($open_tracker)  && $open_tracker,
	'public_index' => isset($public_index)  && $public_index,
);

?><!DOCTYPE html>
<html lang="en">
<head>
	<title>Phoenix Setup</title>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/combine/gh/eustasy/Colors.css@1/colors.min.css,gh/necolas/normalize.css@8/normalize.min.css">
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
	<?php if ( !$settings_writable ): ?>
		<p class="box background-pomegranate color-clouds">
			<code>config/</code> is not writable. Make it writable to proceed with installation.
		</p>
	<?php else: ?>
		<?php if ( $install_error ): ?>
			<p class="box background-pomegranate color-clouds"><?php echo $install_error; ?></p>
		<?php endif; ?>
		<form method="POST" action="">
			<input type="hidden" name="process" value="install">
			<h2>Database</h2>
			<div class="field"><label>Host</label>
				<input type="text" name="db_host" value="<?php echo $f['db_host']; ?>">
			</div>
			<div class="field"><label>Username</label>
				<input type="text" name="db_user" value="<?php echo $f['db_user']; ?>">
			</div>
			<div class="field"><label>Password</label>
				<input type="password" name="db_pass">
			</div>
			<div class="field"><label>Database Name</label>
				<input type="text" name="db_name" value="<?php echo $f['db_name']; ?>">
			</div>
			<div class="field"><label>Table Prefix</label>
				<input type="text" name="db_prefix" value="<?php echo $f['db_prefix']; ?>">
			</div>
			<div class="field checkbox">
				<label><input type="checkbox" name="db_persist" value="1"<?php echo $f['db_persist'] ? ' checked' : ''; ?>>
				Persistent Connections</label>
			</div>
			<h2>Tracker</h2>
			<div class="field checkbox">
				<label><input type="checkbox" name="open_tracker" value="1"<?php echo $f['open_tracker'] ? ' checked' : ''; ?>>
				Open Tracker &mdash; track any announced torrent</label>
			</div>
			<div class="field checkbox">
				<label><input type="checkbox" name="public_index" value="1"<?php echo $f['public_index'] ? ' checked' : ''; ?>>
				Public Index &mdash; list torrents on the index page</label>
			</div>
			<h2>Admin</h2>
			<div class="field"><label>Admin Password (leave blank for no authentication)</label>
				<input type="password" name="admin_password">
			</div>
			<br>
			<input class="button background-belize-hole color-clouds" type="submit" value="Install">
		</form>
	<?php endif; ?>
</body>
</html>
<?php
exit;
