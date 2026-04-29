<?php

// This page is not secure.
// It should not be deployed in a production environment.

// Bootstrap paths before attempting a DB connection.
$settings['root']      = __DIR__.'/';
$settings['functions'] = $settings['root'].'_functions/phoenix/';
$settings['hooks']     = $settings['root'].'_hooks/phoenix/';
$settings['onces']     = $settings['root'].'_onces/phoenix/';
$settings['settings']  = $settings['root'].'_settings/';

require_once $settings['functions'].'function.tracker.error.php';

$config_path   = $settings['settings'].'phoenix.custom.php';
$config_exists = is_readable($config_path);

////	Installation Flow
// Runs when no configuration file exists.
if ( !$config_exists ) {

	error_reporting(0);

	$settings_writable = is_writable($settings['settings']);
	$install_error     = null;

	if (
		$settings_writable &&
		isset($_POST['process']) &&
		$_POST['process'] === 'install'
	) {
		$db_host      = !empty($_POST['db_host'])   ? strip_tags($_POST['db_host'])                              : 'localhost';
		$db_user      = !empty($_POST['db_user'])   ? strip_tags($_POST['db_user'])                              : '';
		$db_pass      =  isset($_POST['db_pass'])   ? $_POST['db_pass']                                         : '';
		// db_name and db_prefix are used as SQL identifiers (backtick-quoted); restrict to safe chars.
		$db_name      = !empty($_POST['db_name'])   ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_name'])   : 'phoenix';
		$db_prefix    = !empty($_POST['db_prefix']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_prefix']) : '';
		$db_persist      = !empty($_POST['db_persist']);
		$open_tracker    = !empty($_POST['open_tracker']);
		$public_index    = !empty($_POST['public_index']);
		$admin_password  = isset($_POST['admin_password']) && $_POST['admin_password'] !== ''
			? password_hash($_POST['admin_password'], PASSWORD_DEFAULT)
			: '';

		// Test the connection before writing anything.
		$test_host = $db_persist ? 'p:'.$db_host : $db_host;
		$test_conn = @mysqli_connect($test_host, $db_user, $db_pass, $db_name);

		if ( !$test_conn ) {
			$install_error = 'Could not connect to the database: '.mysqli_connect_error();
		} else {
			$settings['db_prefix'] = $db_prefix;
			$settings['db_name']   = $db_name;
			require_once $settings['functions'].'function.mysqli.create.database.php';
			if ( !create_database($test_conn, $settings) ) {
				$install_error = 'Connected, but could not create the tables.';
			} else {
				$s = function($v) { return '\''.addslashes($v).'\''; };
				$b = function($v) { return $v ? 'true' : 'false'; };
				$config  = '<?php'.PHP_EOL.PHP_EOL;
				$config .= '$settings[\'db_host\']      = '.$s($db_host).';'.PHP_EOL;
				$config .= '$settings[\'db_user\']      = '.$s($db_user).';'.PHP_EOL;
				$config .= '$settings[\'db_pass\']      = '.$s($db_pass).';'.PHP_EOL;
				$config .= '$settings[\'db_name\']      = '.$s($db_name).';'.PHP_EOL;
				$config .= '$settings[\'db_prefix\']    = '.$s($db_prefix).';'.PHP_EOL;
				$config .= '$settings[\'db_persist\']   = '.$b($db_persist).';'.PHP_EOL;
				$config .= '$settings[\'db_reset\']     = false;'.PHP_EOL;
				$config .= '$settings[\'open_tracker\']    = '.$b($open_tracker).';'.PHP_EOL;
				$config .= '$settings[\'public_index\']    = '.$b($public_index).';'.PHP_EOL;
				$config .= '$settings[\'admin_password\']  = '.$s($admin_password).';'.PHP_EOL;
				if ( file_put_contents($config_path, $config) !== false ) {
					header('Location: admin.php?installed=1');
					exit;
				} else {
					$install_error = 'Connected and created tables, but could not write the configuration file. Check that <code>_settings/</code> is writable.';
				}
			}
			mysqli_close($test_conn);
		}
	}

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
			<code>_settings/</code> is not writable. Make it writable to proceed with installation.
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
}

////	Normal Admin Flow
require_once __DIR__.'/_phoenix.php';
require_once $settings['onces'].'once.auth.php';
require_once $settings['onces'].'once.sanitize.admin.php';
require_once $settings['functions'].'function.mysqli.drop.table.php';
require_once $settings['functions'].'function.mysqli.create.database.php';

// Tables Exist
$tables = array('peers', 'tasks', 'torrents');
$actual = 0;
foreach ( $tables as $table ) {
	$sql = 'SELECT TABLE_NAME '.
	'FROM `information_schema`.`TABLES` '.
	'WHERE TABLE_SCHEMA = \''.$settings['db_name'].'\' '.
	'AND TABLE_NAME = \''.$settings['db_prefix'].$table.'\';';

	$result = mysqli_query($connection, $sql);
	$count = mysqli_num_rows($result);
	if ( !$count ) {
	} else {
		$actual += $count;
	}
}
if ( count($tables) == $actual ) {
	$tables_installed = true;
} else {
	$tables_installed = false;
}

if (
	$Process == 'setup' &&
	(
		$settings['db_reset'] ||
		!$tables_installed
	)
) {
	// MySQL Setup
	$success = true;

	if ( $tables_installed ) {
		if (
			!drop_table($connection, $settings, 'peers') ||
			!drop_table($connection, $settings, 'tasks') ||
			!drop_table($connection, $settings, 'torrents')
		) {
			$success = false;
		}
	}

	// Create the databases.
	if ( !create_database($connection, $settings) ) {
		$success = false;
	}

	if ( $success ) {
		$Message = 'Your MySQL Tracker Database has been setup.';
		require_once $settings['functions'].'function.task.log.php';
		$result = task_log($connection, $settings, 'install', $time);
		$tables_installed = true;
	} else {
		$Message = 'Could not setup the MySQL Database.';
	}

} else if ( $Process == 'clean' ) {
	require_once $settings['functions'].'function.task.clean.php';
	if ( task_clean($connection, $settings, $time) ) {
		$Message = 'The peers list has been cleaned.';
	} else {
		$Message = 'Could not clean the peers list.';
	}

} else if ( $Process == 'optimize' ) {
	require_once $settings['functions'].'function.task.optimize.php';
	if ( task_optimize($connection, $settings, $time) ) {
		$Message = 'Your MySQL Tracker Database has been optimized.';
	} else {
		$Message = 'Could not optimize the MySQL Database.';
	}
}

?><!DOCTYPE html>
<html lang="en">
<head>
	<title>Phoenix Diagnostics and Utilities</title>
	<meta charset="UTF-8">
	<script src="https://cdn.jsdelivr.net/gh/jquery/jquery@3/dist/jquery.min.js"></script>
	<script>
		$(document).ready(function(){
			$('.mysql').submit(function() {
				$('input[type="submit"]').attr('disabled','disabled');
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

<?php

	echo '
	<h1>Compatibility Check</h1>
	<p class="text-center color-9">'.$settings['phoenix_version'].'</p>';

	if ( !empty($settings['admin_password']) ) {
		echo '<p class="text-right"><a href="?logout=1">Log out</a></p>';
	}

	if ( isset($_GET['installed']) ) {
		echo '
		<p class="box background-green-sea color-clouds">Installation complete.</p>';
	}

	// PHP Version
	$php_version = PHP_VERSION;

	// >= 5.5
	if ( version_compare(PHP_VERSION, '5.5.0', '>=') ) {
		echo '
		<p class="box background-green-sea color-clouds">Your PHP version is >= 5.5</p>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
		$php_compat = true;

	// >= 5.0
	} else if ( version_compare(PHP_VERSION, '5.0.0', '>=') ) {
		echo '
		<p class="box background-sun-flower color-midnight-blue">Your PHP version is >= 5.0, but < 5.5.
		We recommend updating to PHP >= 5.5</p>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
		$php_compat = 'Partial';

	// < 5
	} else {
		echo '
		<p class="box background-pomegranate color-clouds">Phoenix is unable to run. Your PHP version is < 5.0</p>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
		$php_compat = false;
	}

	// No MySQL
	if ( !class_exists('mysqli') ) {
		echo '
		<p class="box background-pomegranate color-clouds">Your server does not support MySQL.</p>';
		$mysql_compat = false;

	// Yes MySQL
	} else {
		// Version
		$mysql_version = mysqli_get_client_info();
		$mysql_version = trim(substr($mysql_version, 0, strpos($mysql_version, '-')), 'mysqlnd ');
		echo '
		<p class="box background-green-sea color-clouds">Your server supports MySQL.</p>
		<p class="color-asbestos">MySQL Version: '.$mysql_version.'</p>';
		$mysql_compat = true;

		if ( $tables_installed ) {
			$table_size_query = 'SELECT `data_length` AS `Data`, `index_length` AS `Indexes`, SUM( `data_length` + `index_length` ) AS `Total`, SUM( `data_free` ) AS `Free` FROM `information_schema`.`TABLES` WHERE `table_schema` = \''.$settings['db_name'].'\' AND `table_name` = \'__TABLE_NAME__\' GROUP BY `table_schema`;';
			foreach ( $tables as $table ) {
				$size = str_replace('__TABLE_NAME__', $settings['db_prefix'].$table, $table_size_query);
				$size = mysqli_query($connection, $size, MYSQLI_STORE_RESULT);
				if ( $size ) {
					$table_size[$table] = mysqli_fetch_assoc($size);
				}
			}
			$database_size = 'SELECT `data_length` AS `Data`, `index_length` AS `Indexes`, SUM( `data_length` + `index_length` ) AS `Total`, SUM( `data_free` ) AS `Free` FROM `information_schema`.`TABLES` WHERE `table_schema` = \''.$settings['db_name'].'\' GROUP BY `table_schema`;';
			$database_size = mysqli_query($connection, $database_size, MYSQLI_STORE_RESULT);
			if ( $database_size ) {
				$database_size = mysqli_fetch_assoc($database_size);
			}
			echo '
			<p class="box background-green-sea color-clouds">All your tables are installed.';
			if ( $database_size ) {
				echo ' Their current size is '.number_format($database_size['Total']).' bytes.';
			}
			echo '</p>';
		} else {
			echo '
			<p class="box background-pomegranate color-clouds">Some or all of your tables are not installed.</p>';
		}

		// Database Utilities
		echo '
		<br>
		<h1>Utilities</h1>';

		// $Messages
		if ( isset($Message) ) {
			echo '
			<div class="box background-wisteria color-clouds">
				<h3>'.$Message.'</h3>
			</div>';
		}

		if (
			$settings['db_reset'] ||
			!$tables_installed
		) {
			echo '
			<form class="mysql" action="" method="POST">
				<p class="box background-pomegranate color-clouds">You should set
				<code>$settings[\'db_reset\']</code>
				to false to disable resets,<br>
				or delete <code>admin.php</code> when you\'re up and running.</p>
				<p class="float-left text-left">Install, Upgrade, and Reset</p>
				<input type="hidden" name="process" value="setup">
				<input class="button background-belize-hole color-clouds float-right" type="submit" name="submit" value="Setup">
				<div class="clear"></div>
			</form>';
		} else {
			echo '
				<p class="text-left color-asbestos">Install, Upgrade, and Reset
				<span class="button background-clouds float-right">Disabled</span></p>
				<div class="clear"></div>';
		}
		if ( $tables_installed ) {
			echo '
				<form class="mysql" action="" method="POST">
					<p class="float-left text-left">Clean out redundant peers</p>
					<input type="hidden" name="process" value="clean">
					<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Clean">
					<div class="clear"></div>
				</form>';
			echo '
				<form class="mysql" action="" method="POST">
					<p class="float-left text-left">Check, Analyze, Repair, and Optimize</p>
					<input type="hidden" name="process" value="optimize">
					<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Optimize">
					<div class="clear"></div>
				</form>';
		}

	}

?>
</body>
</html>
