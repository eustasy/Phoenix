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

// Misc Functions //////////////////////////////////////////////////////////////////////////////////

// Check.png
function img0()
{
$img = '
iVBORw0KGgoAAAANSUhEUgAAABIAAAARCAYAAADQWvz5AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAApVJREFUeNqclG1IE3Ecx39397+73W132267M2Fam3MqYTQfIhPFV/Ym
LBgVFJUIIQWWUIbQwwt7EYUw8IX0LnotFs1SiIogNFplojWKSg3Lh3TDqTe33e2uSUgmCtPvuz/f
Lx/+vwd+BGxHGED+8cIWp8/TrBEpVh5fHEbb4bh9Bc2uxuI7C7FFyK/Ye1SJJeP4ViEmhynPfXJ3
20I0ClhCA4QIYCRj3pZBexpL2zUOOApHwLAGgHl1KRL8HdgSyFGdc3hHteOInlSBpQ1gMjHw/V6o
bXl6+WPGIJInLWVNFf4UpIClGDDbOJjrn3o50fvDv+JnDPLWl13lXLyT1HHgOAb0eWVhqOP9xbSl
/gMRgHAaZzeDCEVCicOXe2F2aQYUFAedUSHoH7ghT8rDqxncWmArq+qoDe7rrBnZeSzvOrZ+ZRCG
SpvLO1RGoRhEgWA3w9Tzny/Gesc71+bwnXXuK5ib8WIC5co642yTzuXcT/+QWg3k13kaxP1SJa0j
sFstgEW06Jvbwaa0pfwHiozPjWi6CmE5DKMzY8DUWk7vavV0A4UZGRsjes+X3NRTKlhYDqw8D/23
Bq7J07HQ+vLRxKPR9oSoFiYr8RMUgYBIYGCpyTpkMrEBCdmjdBYlkSkMRMkKoe7Pz770fL27UR+J
9DRV+V00YLBQDr7Y4qUxCmggQfKITrtHKEIaDkK6pORMItJ19qFPkZXZjUF/pcU/yD0UR0q2Enu5
gaCAxWmgEQk8awSrmYfHl/tafg1O9W02WWLtQx5cfEIigsuuyD5gIGgwUgYQRQE+dYX6XvlfX0pH
9IxAK4oOzT8ldIzOrcqtMvMcxCeXww8aAz4lpoa3dXKKThW2Nryt/+Y+6GrIJP9HgAEAq+zTbnKP
p9sAAAAASUVORK5CYII=';

	header('Content-Type: image/png');
	echo base64_decode($img);
	exit;
}

// Delete.png
function img1()
{
$img = '
iVBORw0KGgoAAAANSUhEUgAAABIAAAARCAYAAADQWvz5AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAxVJREFUeNqElNtLG0EUxmdns7fEzUZTgxZrK4lrLGq0Dda7RCh97IPY
G0L/EquiINaXIvXB9qEPllQsqBTaai+opb6UQl+9IEXQVTS6zW2TuEm2Z2IjahUHhp2dmfOb73xz
dhE61m7Y7Y0MRQnogpbPcQVlolhz5uJdl+vBSmdnqsvtHoNX6jyIyDDWiTt3fi40NYVls7k2O09n
IQP19f7g1hauzs2tcjCMvBAITP4HYVnppc/3pUzTvFQsxt52OO79CAbnArq+SZN0XrS1fQgqCpVK
pVBC11G1KFbms2zpwt7eVBZiZVnbaGvr7HVNq41EIihtGMiKEN9kt3e8DwTG6NDBQcgSjxcRJQRi
EFgyeQT7tr8/SZSMtrTMVGhaXTgcRhRFIRpArMmERhRl6Fc4PHvkRbfb7W8vLHwYiscRSqcRAmAO
TaMpVfWXFBdfuRmLNWchGCACQAYUpf/N9nYXOmUq9USWX7c7HI/CiURmwTg4QBwAUwCPQzoUADAc
YuY4NJRI9I/v7nadMDvbiCdwta4qjKsiioL0nR0UDwRQkiiBdDF0M8OgwVisd2J/v/t4LH36Zogn
ecmkLEejlSk4nYZAE6RBuiAIaFDXe96qau/pOHx6wsrzktPpvEyxLMIYIxo6Bq9o6CYYu1i2+Kz6
OqFI4jjbc49npmxtrSkGvhAVGQABwZgY3cjzNXk5Oc75UGj6TJDE87ZhgLhWV+ui0SgykZRAgcDz
iPsHIXNpeDZbLB67xVL6NRicOgGycpw04vF8KllevhXRtENfIMB86Em3gvFvoiQNYAbWDFDYYrVW
2s1m+bOqEphBFwhC0bOKipnipSVvBJRkITkAearrvcTY75HIO5IOUWIQGPhHYD5JqrwkCOVzqvoR
SzTtyF1fLyd1giEFUmyZOtH1/glV7Tkq2I2Nx680zZ8LqTKwjyUHwr4qUfSyGIuZTaUcVzstSX/m
GMZYlCTjvs3Wd87HTw05nX69sdEwfD5jsaFhRWKYayd2lPJ87aTdvtkhin0X/I6oYVken/d6l60M
czU7+VeAAQD3hy6rS/0yjQAAAABJRU5ErkJggg==';

	header('Content-Type: image/png');
	echo base64_decode($img);
	exit;
}

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

// PHP Version Check
function checkPHP()
{
	// PHP Version
	$_GET['php_version'] = PHP_VERSION;
	
	// Check 5.3
	if (version_compare(PHP_VERSION, '5.3.0', '>='))
	{
echo <<<HTML
<tr>
<td class="content_key yes c">PHP</td>
<td class="content_value yes c">{$_GET['php_version']}</td>
<td class="content_desc yes r">Your server supports PHP 5.3+</td>
<td class="content_icon yes"><img src="help.php?load=img_check" class="icon" alt="Supported" /></td>
</tr>

HTML;
	}
	// Check 5.0
	elseif (version_compare(PHP_VERSION, '5.0.0', '>='))
	{
echo <<<HTML
<tr>
<td class="content_key yes c">PHP<</td>
<td class="content_value yes c">{$_GET['php_version']}</td>
<td class="content_desc yes r">Your server supports PHP 5.0+. Update to PHP 5.3 or higher when possible.</td>
<td class="content_icon yes"><img src="help.php?load=img_check" class="icon" alt="Supported" /></td>
</tr>

HTML;
	}
	// Does not support PHP 5
	else
	{
echo <<<HTML
<tr>
<td class="content_key no c">PHP</td>
<td class="content_value no c">N/A</td>
<td class="content_desc no r">Your server does not support PHP 5. Request that your administrator update it.</td>
<td class="content_icon no"><img src="help.php?load=img_cross" class="icon" alt="Not Supported" /></td>
</tr>

HTML;
	}
}

// MySQL ///////////////////////////////////////////////////////////////////////////////////////////

// MySQL Version Check
function checkMySQL()
{
	// Check MySQL
	if (class_exists('mysqli') OR function_exists('mysql_connect'))
	{
		// Version
		$_GET['mysql_version'] = (class_exists('mysqli') ? mysqli_get_client_info() : mysql_get_client_info());
		$_GET['mysql_version'] = trim(substr($_GET['mysql_version'], 0, strpos($_GET['mysql_version'], '-')), 'mysqlnd ');
	
echo <<<HTML
<tr>
<td class="content_key yes c">MySQL</td>
<td class="content_value yes c">{$_GET['mysql_version']}</td>
<td class="content_desc yes r">Your server supports MySQL.</td>
<td class="content_icon yes"><img src="help.php?load=img_check" class="icon" alt="Supported" /></td>
</tr>

HTML;
	}
	// No MySQL
	else
	{
echo <<<HTML
<tr>
<td class="content_key no c">MySQL</td>
<td class="content_value no c">N/A</td>
<td class="content_desc no r">Your server does not support MySQL.</td>
<td class="content_icon no"><img src="help.php?load=img_cross" class="icon" alt="Not Supported" /></td>
</tr>

HTML;
	}
}

// MySQL Util
function utilMySQL()
{
	// Check
	if (isset($_GET['mysql_version']))
	{
echo <<<HTML
<tr>
<td class="diag_item top l"><strong>MySQL</strong></td>
<td class="diag_desc top r"><strong>Description</strong></td>
</tr>
<tr>
<td class="diag_item top l"><ul class="postnav"><li><a href="./help.php?do=setup_mysql">Setup MySQL</a></li></ul></td>
<td class="diag_desc top r">Install / Upgrade and Reset your MySQL Tracker Database.</td>
</tr>
<tr>
<td class="diag_item top l"><ul class="postnav"><li><a href="./help.php?do=optimize_mysql">Optimize MySQL</a></li></ul></td>
<td class="diag_desc top r">Check, Analyze, Repair and Optimize your MySQL Tracker Database.</td>
</tr>

HTML;
	}
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
	peertracker::open();
	
	// setup
	if (
		peertracker::$api->query("DROP TABLE IF EXISTS `{$_SERVER['tracker']['db_prefix']}peers`") &&
		peertracker::$api->query(
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
		peertracker::$api->query("DROP TABLE IF EXISTS `{$_SERVER['tracker']['db_prefix']}tasks`") &&
		peertracker::$api->query(
			"CREATE TABLE IF NOT EXISTS `{$_SERVER['tracker']['db_prefix']}tasks` (" . 
				"`name` varchar(5) NOT NULL," . 
				"`value` int(10) unsigned NOT NULL" . 
			") ENGINE=MyISAM DEFAULT CHARSET=latin1"
		))
	{
		// Check Table
		@peertracker::$api->query("CHECK TABLE `{$_SERVER['tracker']['db_prefix']}peers`");
		
		// no errors, hopefully???
		$_GET['notice'] = 'yes';
		$_GET['message'] = 'Your MySQL Tracker Database has been setup.';
	}
	// error
	else
	{
		$_GET['notice'] = 'no';
		$_GET['message'] = 'Could not setup the MySQL Database.';
	}
	
	// close
	peertracker::close();
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
	peertracker::open();
	
	// optimize
	if (
		peertracker::$api->query("CHECK TABLE `{$_SERVER['tracker']['db_prefix']}peers`") && 
		peertracker::$api->query("ANALYZE TABLE `{$_SERVER['tracker']['db_prefix']}peers`") && 
		peertracker::$api->query("REPAIR TABLE `{$_SERVER['tracker']['db_prefix']}peers`") && 
		peertracker::$api->query("OPTIMIZE TABLE `{$_SERVER['tracker']['db_prefix']}peers`")
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
	peertracker::close();
}

// Display Information /////////////////////////////////////////////////////////////////////////////

// Load Resources
if (isset($_GET['load']))
{
	// Images & JS
	if ($_GET['load'] == 'img_check') img0();
	elseif ($_GET['load'] == 'img_cross') img1();
	elseif ($_GET['load'] == 'js_curvy') js0();
}
// Handle Database Actions
elseif (isset($_GET['do']))
{
	// SQLite3
	if ($_GET['do'] == 'setup_sqlite3') setupSQLite3();
	elseif ($_GET['do'] == 'optimize_sqlite3') optimizeSQLite3();
	// MySQL
	elseif ($_GET['do'] == 'setup_mysql') setupMySQL();
	elseif ($_GET['do'] == 'optimize_mysql') optimizeMySQL();
	// PostgreSQL
}
// Output HTML
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Phoenix Diagnostics and Utilities</title>
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<script type="text/javascript" src="help.php?load=js_curvy"></script>
<style type="text/css">
html,body { margin: 0; padding: 10px 0; background-color: #FFFFFF; color: #000000; text-align: center; font-size: 95%; font-family: arial, sans-serif; }
ul.postnav,ul.postnav li{ margin: 0; padding: 0; list-style-type: none; }
ul.postnav li{ width: 200px; margin: 0; padding: 0; }
ul.postnav a{ display: block; width: 200px; padding: 2px 0; font-weight: bold; text-transform: uppercase; background: #C3D9FF; border: 1px solid #b5d0ff; color: #000000; text-decoration: none; text-align: center; }
ul.postnav a:hover{ background: #b5d0ff; color: #000000; }
h1 { font-size: 1.4em; margin: 0; padding: 0; }
h2 { font-size: 1.2em; margin: 0; padding: 0; }
h3 { font-size: 1.0em; margin: 0; padding: 0; }
.status { margin: 0 auto; padding: 2px 5px; width: 95%; text-align: center; -webkit-border-radius: 5px; -moz-border-radius: 5px; }
.diag_header { margin: 0 auto; padding: 5px; width: 95%; text-align: left; background-color: #C3D9FF; -webkit-border-top-left-radius: 5px; -webkit-border-top-right-radius: 5px; -moz-border-radius-topleft: 5px; -moz-border-radius-topright: 5px; }
.content_wrap { margin: 0 auto; padding: 0 5px 5px 5px; width: 95%; text-align: left; background-color: #C3D9FF; -webkit-border-bottom-left-radius: 5px; -webkit-border-bottom-right-radius: 5px; -moz-border-radius-bottomleft: 5px; -moz-border-radius-bottomright: 5px; }
.content_table { width: 100%; border: 0; background-color: #FFFFFF; }
.content_header { background-color: #C3D9FF; text-align: center; }
.content_key { width: 15%; border-top: 1px solid #C3D9FF; }
.content_value { width: 15%; border-top: 1px solid #C3D9FF; text-align: center; margin: 0; padding: 2px; }
.content_desc { width: 69%; border-top: 1px solid #C3D9FF; text-align: right; margin: 0; padding: 2px; }
.content_icon { width: 1%; border-top: 1px solid #C3D9FF; margin: 0; padding: 0; }
.spacer{ height: 25px; }
.icon{ width: 18px; margin: 0; padding: 0; vertical-align: bottom; border: 0; }
.diag_item { width: 20%; border-top: 1px solid #C3D9FF; margin: 0; padding: 2px; }
.diag_desc { width: 60%; border-top: 1px solid #C3D9FF; margin: 0; padding: 2px; }
.l { text-align: left; }
.r { text-align: right; }
.c { text-align: center; }
.top { background-color: #E5ECF9; }
.yes { background-color: #DDF8CC; }
.no { background-color: #F8CCCC; }
</style>
</head>
<body>
<?php
// Messages
if (isset($_GET['notice']) && isset($_GET['message']))
{
echo <<<HTML
<div class="status {$_GET['notice']}"><h3>{$_GET['message']}</h3></div>
<div>&nbsp;</div>

HTML;
}
?>
<div class="diag_header"><h1>Diagnostics</h1></div>
<div class="content_wrap">
<table class="content_table" cellpadding='0' cellspacing='0'>
<tr>
<td class="content_key top c"><strong>PHP</strong></td>
<td class="content_value top c"><strong>Version</strong></td>
<td class="content_desc top r" colspan="2"><strong>Summary</strong></td>
</tr>
<?php 
checkPHP(); 
?>
<tr>
<td class="content_key top c"><strong>Database</strong></td>
<td class="content_value top c"><strong>Version</strong></td>
<td class="content_desc top r" colspan="2"><strong>Summary</strong></td>
</tr>
<?php
// Database Checks
checkMySQL();
?>
</table>
</div>
<div class="spacer"></div>
<div class="diag_header"><h1>Utilities</h1></div>
<div class="content_wrap">
<table class="content_table" cellpadding='0' cellspacing='0'>
<?php 
// Database Utilities
utilMySQL();
?>
</table>
</div>
</body>
</html>