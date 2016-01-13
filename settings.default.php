<?php

////	DO NOT MODIFY `settings.default.php`
// Copy to `settings.custom.php` and modify there.

$settings = array(

	// General Tracker Options
	'open_tracker'      => true,         /* track anything announced to it */
	'announce_interval' => 1800,         /* how often client will send requests */
	'min_interval'      => 600,          /* how often client can force requests */
	'default_peers'     => 50,           /* default # of peers to announce */
	'max_peers'         => 100,          /* max # of peers to announce */

	// Advanced Tracker Options
	'external_ip'       => true,         /* allow client to specify ip address */
	'default_compact'   => true,         /* force compact announces only */
	'full_scrape'       => true,         /* allow scrapes without info_hash */
	'random_limit'      => 500,          /* if peers > #, use alternate SQL RAND() */
	'clean_idle_peers'  => 10,           /* tweaks % of time tracker attempts idle peer removal */
	                                     /* if you have a busy tracker, you may adjust this */
	                                     /* example: 1 = 1%, 10 = 10%, 50 = 50%, 100 = every time */
	'honor_xff'         => false,        /* If this server is behind a frontend proxy, the client IP */
	                                     /* will come in the form of a X-Forwarded-For. This option */
	                                     /* should only be set if your frontend proxy properly handles */
	                                     /* and filters XFF, else it allows for trivial IP spoofing */

	// General Database Options
	// Can be better overridden with a settings.custom.php file.
	'db_host'           => 'localhost',  /* ip or hostname to mysql server */
	'db_user'           => 'phoenix',    /* username used to connect to mysql */
	'db_pass'           => 'Password1',  /* password used to connect to mysql */
	'db_name'           => 'phoenix',    /* name of the Phoenix database */

	// Advanced Database Options
	'db_prefix'         => '',           /* name prefixes for the Phoenix tables */
	'db_persist'        => true,         /* use persistent connections if available. */
	'db_reset'          => true,         /* allow database to be reset in admin */

	'phoenix_version'  => 'Phoenix Procedural v.2.0 2015-08-20 14:22:00Z eustasy',

);
