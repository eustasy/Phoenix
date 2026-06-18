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
/* track anything announced to it; off = closed/private tracker (BEP 27) */
$settings['open_tracker'] = '%open_tracker%';
/* how often client will send requests */
$settings['announce_interval'] = 300; // 5 minutes
/* how often client can force requests */
$settings['min_interval'] = 60; // 1 minute
/* default # of peers to announce */
$settings['default_peers'] = 50;
/* max # of peers to announce */
$settings['max_peers'] = 100;

////	Advanced Tracker Options
/* allow client to specify ip address */
$settings['external_ip'] = false;
/* default to compact announces when not specified */
$settings['default_compact'] = true;
/* echo the client's own public IP back in announce responses (BEP 24) */
$settings['announce_external_ip'] = true;
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
/* include torrent meta (filename, files, trackers, webseeds) */
/* in the public index output */
$settings['index_show_meta'] = false;
/* canonical announce URL of this tracker, embedded as the first */
/* tracker in index magnet links; empty = omit */
$settings['announce_url'] = '';

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
/* base32 TOTP secret for optional admin two-factor auth; empty = no 2FA. */
/* Set during install (needs the eustasy/authenticatron package + ext-gd for */
/* the QR). Lost your authenticator? Remove this line to disable 2FA. */
$settings['admin_totp_secret'] = '';
/* rows per page on the admin Peers (swarm-wide) listing */
$settings['admin_peers_limit'] = 200;

////	API Options
/* API keys permitted to use the management API under public/api/, as */
/* 'user' => 'key' pairs. Sent as an `Authorization: Bearer <key>` header. */
/* The user a key belongs to is recorded on each torrent it adds, and scopes */
/* the torrent list and the list/delist/delete actions to that user's own */
/* torrents. Empty array disables the API. The reserved owner '*' is the */
/* admin: its key may act on ANY torrent (and see the full list), including */
/* announce-created rows with no owner. A logged-in admin.php session is the */
/* admin too (with a CSRF token for writes). Use distinct, lowercase user */
/* names (the user column collates case-insensitively). */
$settings['api_keys'] = [];
/* max accepted .torrent upload size in bytes for server-side parsing */
$settings['torrent_upload_max'] = 1048576;
/* allow torrents to be deleted via the API. Off by default since */
/* deletion is destructive; the '*' admin can always delete regardless. */
$settings['api_allow_delete'] = false;

////	Stat-Tracking Options
/* log torrent events to the events table; off by default. */
/* the table exists from install, so this is a pure config flip */
$settings['stats_enabled'] = false;
/* which announce events to log. 'completed' matches bits; */
/* 'started'/'stopped' are higher-volume. 'access'/'change' are */
/* intentionally unsupported — they fire on every keepalive */
/* announce and would flood the table */
$settings['stats_events'] = ['completed'];
/* enrich events (and the admin Geography map) with a minified geo */
/* location (country + continent only). requires the suggested */
/* geoip2/geoip2 composer library and a GeoLite2-Country database */
$settings['stats_geo'] = false;
/* path to a MaxMind GeoLite2-Country.mmdb. empty = auto-discover */
/* /usr/share/GeoIP, /var/lib/GeoIP, then config/. not shipped — */
/* MaxMind's license forbids redistribution */
$settings['stats_geo_database'] = '';
/* days to keep logged events; 0 = keep forever. pruned during the */
/* regular cleanup (announce-time or cron), even if stats_enabled */
/* has since been turned off */
$settings['stats_retention'] = 0;

////	Backup Options
/* absolute path to backup directory; empty = backups/ in the project root */
$settings['backup_dir'] = '';
/* delete backups older than this many days */
$settings['backup_rotate'] = 30;
