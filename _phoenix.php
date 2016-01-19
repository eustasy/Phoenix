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

$settings['phoenix_version'] = 'Phoenix Procedural v.2.0 2015-08-20 14:22:00Z eustasy';

// If the root isn't a directory up, modify that here.
$settings['root'] = __DIR__.'/';
// Don't modify these, they'll figure it out.
$settings['functions'] = $settings['root'].'_functions/phoenix/';
$settings['hooks']     = $settings['root'].'_hooks/phoenix/';
$settings['onces']     = $settings['root'].'_onces/phoenix/';
$settings['settings']  = $settings['root'].'_settings/';

////	Error Level
error_reporting(E_ALL);
// error_reporting(E_ALL & ~E_WARNING);
// error_reporting(E_ALL | E_STRICT | E_DEPRECATED);
// error_reporting(0);

// Ignore Disconnects
ignore_user_abort(true);
ini_set('default_charset', 'iso-8859-1');

$time = time();
$chance = mt_rand(1, 100);

// Allow Access from Anywhere
header('Access-Control-Allow-Origin: *');

// Override the default database variables with this.
include $settings['settings'].'phoenix.default.php';
if ( is_readable($settings['settings'].'phoenix.custom.php') ) {
	include $settings['settings'].'phoenix.custom.php';
}

require_once $settings['functions'].'function.tracker.error.php';
// DB_Connect must be loaded after tracker_error and settings.
require_once $settings['onces'].'once.db.connect.php';

if ( !$settings['open_tracker'] ) {
	require_once $settings['functions'].'function.tracker.allowed.php';
	$allowed_torrents = tracker_allowed($connection, $settings);
}
