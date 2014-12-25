<?php

// License Information /////////////////////////////////////////////////////////////////////////////

/* 
 * Phoenix - OpenSource BitTorrent Tracker
 * Copyright 2015 Phoenix Team
 *
 * Phoenix is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Phoenix is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Phoenix.  If not, see <http://www.gnu.org/licenses/>.
 */

// Note ////////////////////////////////////////////////////////////////////////////////////////////

// this 'help.php' script is by no means an especially efficient or clean script.
// for the time being, it gets the job done. it may be cleaned up at a later time.

// Enviroment Runtime //////////////////////////////////////////////////////////////////////////////

// error level
error_reporting(E_ERROR | E_PARSE);
//error_reporting(E_ALL & ~E_WARNING);
//error_reporting(E_ALL | E_STRICT | E_DEPRECATED);

// ignore disconnects
ignore_user_abort(true);

// Locate File Path
function findFile($dir, $file)
{
	// open dir & scan dir
	if (!$h = @opendir($dir)) return false;
	while (false !== ($f = readdir($h)))
	{
		// filter
		if ($f != '.' && $f != '..')
		{
			// file match
			if ($f == $file)
			{
				// found
				$_GET['found_file_path'] = $dir . '/' . $f;
				return true;
			}
			// scan dir
			elseif (
				// dir check
				is_dir($dir . '/' . $f) && 
				// file found?
				findFile($dir . '/' . $f, $file) === true
			) return true;
		}
	}
	@closedir($h);
	
	// nothing found
	return false;
}

// MySQL Setup
function setupMySQL()
{
	// we need to locate phoenix.php
	// first, try the most obvious location.. which should be in the 
	// same directory as the ./help.php file being ran
	if (is_readable('./phoenix.php'))
	{
		// require
		require './phoenix.php';
	}
	// unfortunately, it does not seem the file is located in the current
	// directory, we will recurse the paths below and attempt to locate it
	elseif (findFile(realpath('.'), 'phoenix.php'))
	{
		// require
		chdir(dirname($_GET['found_file_path']));
		require './phoenix.php';
	}
	// unable to find the file, might as well quit
	else
	{
		$_GET['notice'] = 'no';
		$_GET['message'] = '' . 
			"Could not locate the <em>phoenix.php</em> file. " .
			"Make sure all of the necessary tracker files have been uploaded. ";
		return;
	}

	// open db
	phoenix::open();
	
	// setup
	if (
		phoenix::$api->query("DROP TABLE IF EXISTS `{$_SERVER['tracker']['db_prefix']}peers`") &&
		phoenix::$api->query(
			"CREATE TABLE IF NOT EXISTS `{$_SERVER['tracker']['db_prefix']}peers` (" .
				"`info_hash` binary(20) NOT NULL," .
				"`peer_id` binary(20) NOT NULL," .
				"`compact` binary(6) NOT NULL," . 
				"`ip` char(15) NOT NULL," .
				"`port` smallint(5) unsigned NOT NULL," .
				"`state` tinyint(1) unsigned NOT NULL DEFAULT '0'," .
				"`updated` int(10) unsigned NOT NULL," .
				"PRIMARY KEY (`info_hash`,`peer_id`)" .
			") ENGINE=MyISAM DEFAULT CHARSET=latin1"
		) && 
		phoenix::$api->query("DROP TABLE IF EXISTS `{$_SERVER['tracker']['db_prefix']}tasks`") &&
		phoenix::$api->query(
			"CREATE TABLE IF NOT EXISTS `{$_SERVER['tracker']['db_prefix']}tasks` (" . 
				"`name` varchar(5) NOT NULL," . 
				"`value` int(10) unsigned NOT NULL" . 
			") ENGINE=MyISAM DEFAULT CHARSET=latin1"
		))
	{
		// Check Table
		phoenix::$api->query('CHECK TABLE `'.$_SERVER['tracker']['db_prefix'].'peers`');
		
		// no errors, hopefully???
		$_GET['message'] = 'Your MySQL Tracker Database has been setup.';
	}
	// error
	else
	{
		$_GET['message'] = 'Could not setup the MySQL Database.';
	}

	phoenix::close();

}

// MySQL Optimizer
function optimizeMySQL()
{
	// we need to locate phoenix.php
	// first, try the most obvious location.. which should be in the 
	// same directory as the ./help.php file being ran
	if (is_readable('./phoenix.php'))
	{
		// require
		require './phoenix.php';
	}
	// unfortunately, it does not seem the file is located in the current
	// directory, we will recurse the paths below and attempt to locate it
	elseif (findFile(realpath('.'), 'phoenix.php'))
	{
		// require
		chdir(dirname($_GET['found_file_path']));
		require './phoenix.php';
	}
	// unable to find the file, might as well quit
	else
	{
		$_GET['notice'] = 'no';
		$_GET['message'] = '' . 
			"Could not locate the <em>phoenix.php</em> file. " .
			"Make sure all of the necessary tracker files have been uploaded. ";
		return;
	}

	// open db
	phoenix::open();
	
	// optimize
	if (
		phoenix::$api->query("CHECK TABLE `{$_SERVER['tracker']['db_prefix']}peers`") && 
		phoenix::$api->query("ANALYZE TABLE `{$_SERVER['tracker']['db_prefix']}peers`") && 
		phoenix::$api->query("REPAIR TABLE `{$_SERVER['tracker']['db_prefix']}peers`") && 
		phoenix::$api->query("OPTIMIZE TABLE `{$_SERVER['tracker']['db_prefix']}peers`")
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
	
	// close
	phoenix::close();
}


// Handle Database Actions
if (isset($_GET['do'])) {
	// MySQL
	if ($_GET['do'] == 'setup_mysql') setupMySQL();
	else if ($_GET['do'] == 'optimize_mysql') optimizeMySQL();
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

		// Database Utilities
		echo '
		<br>
		<h1>Utilities</h1>';

		// Messages
		if ( isset($_GET['notice']) && isset($_GET['message']) ) {
			echo '
			<div class="box background-wisteria color-clouds">
				<h3><div class="status '.$_GET['notice'].'">'.$_GET['message'].'</div></h3>
			</div>';
		}

		// TODO
		// Buttons should be POST form submits to prevent repeat on reload.
		// Buttons should disable on click to prevent double submission.
		echo '
		<p class="text-left">Install, Upgrade and Reset <a class="button background-belize-hole color-clouds float-right" href="./help.php?do=setup_mysql">Setup MySQL</a></p>
		<p class="text-left">Check, Analyze, Repair and Optimize <a class="button background-belize-hole color-clouds float-right" href="./help.php?do=optimize_mysql">Optimize MySQL</a></p>';

	}

	echo '
</body>
</html>';
