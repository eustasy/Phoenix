<?php

/*
 * Phoenix - A modern fork of PeerTracker, a lightweight PHP/SQL BitTorrent Tracker.
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

// Error Level
error_reporting(E_ALL);
// error_reporting(E_ALL & ~E_WARNING);
// error_reporting(E_ALL | E_STRICT | E_DEPRECATED);

// Ignore Disconnects
ignore_user_abort(true);
ini_set('default_charset', 'iso-8859-1');

$time = time();

// IF MAGIC QUOTES
if ( get_magic_quotes_gpc() ) {
	// Strip auto-escaped data.
	if ( isset($_GET['info_hash']) ) {
		$_GET['info_hash'] = stripslashes($_GET['info_hash']);
	}
	if ( isset($_GET['peer_id']) ) {
		$_GET['peer_id'] = stripslashes($_GET['peer_id']);
	}
} // END IF MAGIC QUOTES

// IF BINARY
if (
	isset($_GET['info_hash']) &&
	strlen($_GET['info_hash']) == 20
) {
	$_GET['info_hash'] = bin2hex($_GET['info_hash']);
}
if (
	isset($_GET['peer_id']) &&
	strlen($_GET['peer_id']) == 20
) {
	$_GET['peer_id'] = bin2hex($_GET['peer_id']);
}
// END IF BINARY

// Allow Access from Anywhere
header('Access-Control-Allow-Origin: *');

// Override the default database variables with this.
include __DIR__.'/settings.default.php';
if ( is_readable(__DIR__.'/settings.custom.php') ) {
	include __DIR__.'/settings.custom.php';
}

// require_once __DIR__.'/once.db.connect.php';
require_once __DIR__.'/function.tracker.error.php';
// require_once __DIR__.'/function.tracker.stats.php';

if ( !$settings['open_tracker'] ) {
	require_once __DIR__.'/function.tracker.allowed.php';
	$torrents = tracker_allowed();
}
