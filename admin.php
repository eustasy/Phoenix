<?php

// This page is not secure.
// It should not be deployed in a production environment.

require_once __DIR__.'/_phoenix.php';
require_once $settings['onces'].'once.sanitize.admin.php';
require_once $settings['functions'].'function.mysqli.drop.table.php';

if (
	$Process == 'setup' &&
	$settings['db_reset']
) {
	// MySQL Setup
	$success = true;

	if (
		!drop_table($connection, $settings, 'peers') ||
		!drop_table($connection, $settings, 'tasks') ||
		!drop_table($connection, $settings, 'torrents')
	) {
		$success = false;
	}

	$result = mysqli_query($connection,
		'CREATE TABLE IF NOT EXISTS `'.$settings['db_prefix'].'peers` (' .
			'`info_hash` varchar(40) NOT NULL,' .
			'`peer_id` varchar(40) NOT NULL,' .
			'`compactv4` varchar(12) NOT NULL,' .
			'`compactv6` varchar(36) NOT NULL,' .
			'`ipv4` char(15) NOT NULL DEFAULT \'0\',' .
			'`ipv6` char(39) NOT NULL DEFAULT \'0\',' .
			'`portv4` smallint(5) unsigned NOT NULL,' .
			'`portv6` smallint(5) unsigned NOT NULL,' .
			'`left` int(100) unsigned NOT NULL DEFAULT \'0\',' .
			'`state` tinyint(1) unsigned NOT NULL DEFAULT \'0\',' .
			'`updated` int(10) unsigned NOT NULL,' .
			'PRIMARY KEY (`info_hash`,`peer_id`)' .
		') ENGINE=MyISAM DEFAULT CHARSET=latin1;'
	);
	if ( !$result ) {
		echo mysqli_error($connection);
		$success = false;
	}
	$result = mysqli_query($connection,
		'CREATE TABLE IF NOT EXISTS `'.$settings['db_prefix'].'tasks` (' .
			'`name` varchar(16) NOT NULL,' .
			'`value` int(10) NOT NULL,' .
			'PRIMARY KEY (`name`)' .
		') ENGINE=MyISAM DEFAULT CHARSET=latin1;'
	);
	if ( !$result ) {
		echo mysqli_error($connection);
		$success = false;
	}
	$result = mysqli_query($connection,
		'CREATE TABLE IF NOT EXISTS `'.$settings['db_prefix'].'torrents` (' .
			'`name` varchar(255) NULL,' .
			'`info_hash` varchar(40) NOT NULL,' .
			'`downloads` int(10) unsigned NOT NULL DEFAULT \'0\',' .
			'PRIMARY KEY (`info_hash`)' .
		') ENGINE=MyISAM DEFAULT CHARSET=latin1;'
	);
	if ( !$result ) {
		echo mysqli_error($connection);
		$success = false;
	}

	if ( $success ) {
		$Message = 'Your MySQL Tracker Database has been setup.';
		require_once $settings['functions'].'function.task.log.php';
		task($connection, $settings, 'install', $time);
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
	<script src="https://cdn.jsdelivr.net/g/jquery"></script>
	<script>
		$(document).ready(function(){
			$('.mysql').submit(function() {
				$('input[type="submit"]').attr('disabled','disabled');
			});
		});
	</script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/g/normalize,colors.css">
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

	// PHP Version
	$php_version = PHP_VERSION;

	// >= 5.3
	if ( version_compare(PHP_VERSION, '5.5.0', '>=') ) {
		echo '
		<p class="box background-green-sea color-clouds">Your PHP version is >= 5.5</td>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
		$php_compat = true;

	// >= 5.0
	} else if ( version_compare(PHP_VERSION, '5.0.0', '>=') ) {
		echo '
		<p class="box background-sun-flower color-midnight-blue">Your PHP version is >= 5.0, but < 5.5.
		We recommend updating to PHP >= 5.5</p]>
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

		foreach ( $tables as $table ) {
			$sql = 'SELECT TABLE_NAME '.
			'FROM `information_schema`.`TABLES` '.
			'WHERE TABLE_SCHEMA = \''.$settings['db_name'].'\' '.
			'AND TABLE_NAME = \''.$settings['db_prefix'].$table.'\';';

			$result = mysqli_query($connection, $sql);
			$count = mysqli_num_rows($result);
			if ( !$count ) {
				echo '
		<p class="box background-pomegranate color-clouds">The table "'.$table.'" is not installed.</td>';
			} else {
				$actual += $count;
			}
		}
		if ( count($tables) == $actual ) {
			$tables_installed = true;
		} else {
			$tables_installed = false;
		}

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
				echo 'Their current size is '.number_format($database_size['Total']).' bytes.';
			}
			echo '</td>';
		}

		// Database Utilities
		echo '
		<br>
		<h1>Utilities</h1>';

		// $Messages
		if ( isset($Message) ) {
			echo '
			<div class="box background-wisteria color-clouds">
				<h3>'.$Message.'</div></h3>
			</div>';
		}

		if ( $settings['db_reset'] ) {
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

	echo '
</body>
</html>';
