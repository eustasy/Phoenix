<?php

/*
 * Phoenix - A modern fork of PeerTracker, a lightweight PHP/SQL BitTorrent Tracker.
 * Copyright 2015 Phoenix Team
 *
 * magnet:
 	HASH
	?xt=urn:btih:5c5978d6a76b960bb0504433ff6b408b183ebf38
	NAME
	&dn=elementaryos-stable-amd64.20130810.iso
	TRACKERS
	&tr=udp%3A%2F%2Ftracker.publicbt.com%3A80%2Fannounce
	&tr=udp%3A%2F%2Ftracker.openbittorrent.com%3A80%2Fannounce
	&tr=http%3A%2F%2F127.0.0.1%2Fphoenix%2Fannounce.php
	TORRENT
	&xs=http%3A%2F%2Felementaryos.org%2Fdownloads%2Felementaryos-stable-amd64.20130810.iso.torrent
	DATA
	&ws=http%3A%2F%2Fsuberb-sea2.dl.sourceforge.net%2Fproject%2Felementaryos%2Fstable%2Felementaryos-stable-amd64.20130810.iso
	&ws=http%3A%2F%2Fignum.dl.sourceforge.net%2Fproject%2Felementaryos%2Fstable%2Felementaryos-stable-amd64.20130810.iso
	&ws=http%3A%2F%2Fheanet.dl.sourceforge.net%2Fproject%2Felementaryos%2Fstable%2Felementaryos-stable-amd64.20130810.iso
	&ws=http%3A%2F%2Fcitylan.dl.sourceforge.net%2Fproject%2Felementaryos%2Fstable%2Felementaryos-stable-amd64.20130810.iso
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

// error level
error_reporting(E_ERROR | E_PARSE);
//error_reporting(E_ALL & ~E_WARNING);
//error_reporting(E_ALL | E_STRICT | E_DEPRECATED);

// ignore disconnects
ignore_user_abort(true);

$_SERVER['tracker'] = array(

	// General Tracker Options
	'open_tracker'      => true,          /* track anything announced to it */
	'announce_interval' => 1800,          /* how often client will send requests */
	'min_interval'      => 600,           /* how often client can force requests */
	'default_peers'     => 50,            /* default # of peers to announce */
	'max_peers'         => 100,           /* max # of peers to announce */

	// Advanced Tracker Options
	'external_ip'       => true,          /* allow client to specify ip address */
	'force_compact'     => false,         /* force compact announces only */
	'full_scrape'       => false,         /* allow scrapes without info_hash */
	'random_limit'      => 500,           /* if peers > #, use alternate SQL RAND() */
	'clean_idle_peers'  => 1,             /* tweaks % of time tracker attempts idle peer removal */
	                                      /* if you have a busy tracker, you may adjust this */
	                                      /* example: 10 = 10%, 20 = 5%, 50 = 2%, 100 = 1% */

	// General Database Options
	// Can be better overridden with a config.php file.
	'db_host'           => 'localhost',   /* ip or hostname to mysql server */
	'db_user'           => 'root',        /* username used to connect to mysql */
	'db_pass'           => '',            /* password used to connect to mysql */
	'db_name'           => 'phoenix',     /* name of the Phoenix database */

	// Advanced Database Options
	'db_prefix'         => '',            /* name prefixes for the Phoenix tables */
	'db_persist'        => false,         /* use persistent connections if available. */
	'db_reset'          => true,          /* allow database to be reset in admin */

);

// Override the default database variables with this.
if ( is_readable(__DIR__.'/config.php') ) {
	include __DIR__.'/config.php';
}

// Override the default database variables with this.
if ( is_readable(__DIR__.'/class.mysqli.php') ) {
	include __DIR__.'/class.mysqli.php';
} else {
	// TODO Error
}

// Override the default database variables with this.
if ( is_readable(__DIR__.'/class.phoenix.php') ) {
	include __DIR__.'/class.phoenix.php';
} else {
	// TODO Error
}

// fatal error, stop execution
function tracker_error($error) {
	exit('d14:Failure Reason'.strlen($error). ":{$error}e");
}