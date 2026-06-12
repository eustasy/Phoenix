<?php

////	DO NOT MODIFY `phoenix.default.php`
// Copy to `phoenix.custom.php` and modify there.

////	General Database Options
/* ip or hostname to mysql server */
$settings['db_host'] = '%db_host%';
/* username used to connect to mysql */
$settings['db_user'] = '%db_user%';
/* password used to connect to mysql */
$settings['db_pass'] = '%db_pass%';
/* name of the Phoenix database */
$settings['db_name'] = '%db_name%';
/* allow database to be reset in admin */
$settings['db_reset'] = false;

////	Advanced Database Options
/* name prefixes for the Phoenix tables */
$settings['db_prefix'] = '%db_prefix%';
/* use persistent connections if available. */
$settings['db_persist'] = '%db_persist%';

////	General Tracker Options
/* track anything announced to it */
$settings['open_tracker'] = '%open_tracker%';
/* how often client will send requests */
$settings['announce_interval'] = 1800;
/* how often client can force requests */
$settings['min_interval'] = 600;
/* default # of peers to announce */
$settings['default_peers'] = 50;
/* max # of peers to announce */
$settings['max_peers'] = 100;

////	Advanced Tracker Options
/* allow client to specify ip address */
$settings['external_ip'] = false;
/* default to compact announces when not specified */
$settings['default_compact'] = true;
/* allow scrapes with no info_hash, which return EVERY torrent's stats. */
/* Conventional for open trackers. Set false on a closed/private tracker: */
/* a full scrape ignores the allowed-torrents filter, so leaving it on */
/* exposes your whole torrent list to anyone who scrapes. */
$settings['full_scrape'] = true;
/* randomise peer selection to spread load */
$settings['random_peers'] = true;
/* minimum swarm size before RAND() is used */
$settings['random_limit'] = 500;
/* tweaks % of time tracker attempts idle peer removal */
/* if you have a busy tracker, you may adjust this */
/* example: 1 = 1%, 10 = 10%, 50 = 50%, 100 = every time */
$settings['clean_with_requests'] = 1;
/* should your tracker clean with cron */
/* means clean_with_requests can be disabled for faster responses */
$settings['clean_with_cron'] = false;
/* If this server is behind a frontend proxy, the client IP */
/* will come in the form of a X-Forwarded-For. This option */
/* should only be set if your frontend proxy properly handles */
/* and filters XFF, else it allows for trivial IP spoofing */
$settings['honor_xff'] = false;
/* CIDR ranges of trusted reverse proxies. When honor_xff is on, an */
/* X-Forwarded-For header is honored only from these ranges; leave */
/* empty to honor it from any peer (for proxies without a stable IP */
/* range). See APACHE.md / NGINX.md. */
$settings['trusted_proxies'] = [];
/* drop private (RFC 1918) and reserved addresses when */
/* resolving peers, so they are never handed out to the swarm. */
/* Lets a private REMOTE_ADDR (NAT/proxy) fall through to a */
/* public external_ip, per BEP 3. Set false for LAN/same-NAT */
/* trackers, where peers legitimately have private addresses. */
$settings['reject_private_ips'] = true;
/* serve a public index of listed torrents */
$settings['public_index'] = false;

////	Logging & Debugging
/* absolute path for PHP's error log; empty = server/PHP default */
$settings['error_log'] = '';
/* surface + verbosely log errors for local troubleshooting. */
/* NEVER enable in production: display_errors corrupts bencode */
/* responses and discloses internals. Errors are always logged. */
$settings['debug'] = false;

////	Admin Options
/* bcrypt hash of the admin password; empty = no auth */
/* WARNING: set this or delete public/admin.php when you're up and running */
$settings['admin_password'] = '';
/* seconds to delay after a failed admin login (brute-force throttle); 0 disables */
$settings['admin_login_delay'] = 2;
/* cap on the escalating per-session login delay */
$settings['admin_login_delay_max'] = 8;

////	API Options
/* API keys permitted to add torrents via public/api.php, as */
/* 'user' => 'key' pairs. The user a key belongs to is recorded */
/* on each torrent it adds. Empty array disables the API. */
$settings['api_keys'] = [];

////	Backup Options
/* absolute path to backup directory; empty = backups/ in the project root */
$settings['backup_dir'] = '';
/* delete backups older than this many days */
$settings['backup_rotate'] = 30;
