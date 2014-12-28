<?php

// TODO Secure

require_once __DIR__.'/phoenix.php';

// MySQL Setup
function setupMySQL() {

	require_once __DIR__.'/once.db.connect.php';

	if (
		mysqli_query($connection, "DROP TABLE IF EXISTS `{$settings['db_prefix']}peers`") &&
		mysqli_query($connection,
			"CREATE TABLE IF NOT EXISTS `{$settings['db_prefix']}peers` (" .
				"`info_hash` binary(20) NOT NULL," .
				"`peer_id` binary(20) NOT NULL," .
				"`compact` binary(6) NOT NULL," .
				"`ip` char(15) NOT NULL," .
				"`port` smallint(5) unsigned NOT NULL," .
				"`left` int(1000) unsigned NOT NULL DEFAULT '-1'," .
				"`state` tinyint(1) unsigned NOT NULL DEFAULT '0'," .
				"`updated` int(10) unsigned NOT NULL," .
				"PRIMARY KEY (`info_hash`,`peer_id`)" .
			") ENGINE=MyISAM DEFAULT CHARSET=latin1"
		) &&
		mysqli_query($connection, "DROP TABLE IF EXISTS `{$settings['db_prefix']}tasks`") &&
		mysqli_query($connection,
			"CREATE TABLE IF NOT EXISTS `{$settings['db_prefix']}tasks` (" .
				"`name` varchar(5) NOT NULL," .
				"`value` int(10) unsigned NOT NULL" .
			") ENGINE=MyISAM DEFAULT CHARSET=latin1"
		) &&
		mysqli_query($connection,
			"CREATE TABLE IF NOT EXISTS `{$settings['db_prefix']}torrents` (" .
				"`name` varchar(255) NOT NULL," .
			") ENGINE=MyISAM DEFAULT CHARSET=latin1"
		)
	) {
		// Check Table
		mysqli_query($connection, 'CHECK TABLE `'.$settings['db_prefix'].'peers`');
		// no errors, hopefully???
		$_GET['message'] = 'Your MySQL Tracker Database has been setup.';
	}
	// error
	else
	{
		$_GET['message'] = 'Could not setup the MySQL Database.';
	}

}

// MySQL Optimizer
function optimizeMySQL() {

	require_once __DIR__.'/once.db.connect.php';

	// optimize
	if (
		mysqli_query($connection, "CHECK TABLE `{$settings['db_prefix']}peers`") &&
		mysqli_query($connection, "ANALYZE TABLE `{$settings['db_prefix']}peers`") &&
		mysqli_query($connection, "REPAIR TABLE `{$settings['db_prefix']}peers`") &&
		mysqli_query($connection, "OPTIMIZE TABLE `{$settings['db_prefix']}peers`")
	)
	{
		// no errors, hopefully???
		$_GET['notice'] = 'yes';
		$_GET['message'] = 'Your MySQL Tracker Database has been optimized.';
	}
	// error
	else
	{
		$_GET['notice'] = 'no';
		$_GET['message'] = 'Could not optimize the MySQL Database.';
	}

}


// Handle Database Actions
if (isset($_GET['do'])) {
	// MySQL
	if (
		$_GET['do'] == 'setup' &&
		$settings['db_name']
	) {
		setupMySQL();
	} else if ($_GET['do'] == 'optimize') {
		optimizeMySQL();
	}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Phoenix Diagnostics and Utilities</title>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="//cdn.jsdelivr.net/g/normalize,colors.css">
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
		.box {
			padding: 1em;
		}
		.button {
			border-radius: .2em;
			padding: .3em;
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
	<h1>Compatibility Check</h1>';

	// PHP Version
	$php_version = PHP_VERSION;

	// >= 5.3
	if ( version_compare(PHP_VERSION, '5.3.0', '>=') ) {
		echo '
		<p class="box background-green-sea color-clouds">Your PHP version is >= 5.3</td>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
		$php_compat = true;

	// >= 5.0
	} else if ( version_compare(PHP_VERSION, '5.0.0', '>=') ) {
		echo '
		<p class="box background-sun-flower color-midnight-blue">Your PHP version is >= 5.0, but < 5.3. We recommend updating to PHP >= 5.3</td>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
		$php_compat = 'Partial';

	// < 5
	} else {
		echo '
		<p class="box background-pomegranate color-clouds">Phoenix is unable to run. Your PHP version is < 5.0</td>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
		$php_compat = false;
	}

	// No MySQL
	if ( !class_exists('mysqli') ) {
		echo '
		<p class="box background-pomegranate color-clouds">Your server does not support MySQL.</td>';
		$mysql_compat = false;

	// Yes MySQL
	} else {

		// Version
		$mysql_version = mysqli_get_client_info();
		$mysql_version = trim(substr($mysql_version, 0, strpos($mysql_version, '-')), 'mysqlnd ');
		echo '
		<p class="box background-green-sea color-clouds">Your server supports MySQL.</td>
		<p class="color-asbestos">MySQL Version: '.$mysql_version.'</p>';
		$mysql_compat = true;

		// Tables Exist
		$tables = array('peers', 'tasks', 'torrents');
		$actual = 0;
		require_once __DIR__.'/once.db.connect.php';
		foreach ( $tables as $table ) {
			$sql = 'SELECT * FROM `information_schema`.`TABLES`';
			$sql .= " WHERE TABLE_SCHEMA = '{$settings['db_name']}' AND TABLE_NAME = '{$settings['db_prefix']}{$table}'";
			$result = mysqli_query($connection, $sql);
			if ( !$result->num_rows ) {
				echo '
		<p class="box background-pomegranate color-clouds">The table "'.$table.'" is not installed.</td>';
			} else {
				$actual += $result->num_rows;
			}
		}
		phoenix::close();
		if ( count($tables) == $actual ) {
			echo '
		<p class="box background-green-sea color-clouds">All your tables are installed.</td>';
			$tables = true;
		} else {
			$tables = false;
		}


		// Database Utilities
		echo '
		<br>
		<h1>Utilities</h1>';

		// Messages
		if ( isset($_GET['message']) ) {
			echo '
			<div class="box background-wisteria color-clouds">
				<h3>'.$_GET['message'].'</div></h3>
			</div>';
		}

		// TODO
		// Buttons should be POST form submits to prevent repeat on reload.
		// Buttons should disable on click to prevent double submission.
		if ( $settings['db_name'] ) {
			echo '
			<p class="text-left">Install, Upgrade and Reset <a class="button background-belize-hole color-clouds float-right" href="?do=setup">Setup</a></p>';
		} else {
			echo '
			<p class="text-left color-asbestos">Install, Upgrade and Reset <span class="button background-clouds float-right">Disabled</span></p>';
		}
		echo '
		<p class="text-left">Check, Analyze, Repair and Optimize <a class="button background-belize-hole color-clouds float-right" href="?do=optimize">Optimize</a></p>';

	}

	echo '
</body>
</html>';