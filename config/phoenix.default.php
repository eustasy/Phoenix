<?php

////	DO NOT MODIFY `phoenix.default.php`
// Copy to `phoenix.custom.php` and modify there.

////	General Database Options
// db_host, db_user, and db_name are required: the tracker treats them as "not
// configured" while empty (the installer fills them in for you). db_pass may be
// empty for passwordless local auth.
/* ip or hostname to mysql server */
$settings['db_host'] = '';
/* username used to connect to mysql */
$settings['db_user'] = '';
/* password used to connect to mysql */
$settings['db_pass'] = '';
/* name of the Phoenix database */
$settings['db_name'] = '';
/* allow the admin Utilities page to DROP and recreate all Phoenix tables */
/* (destructive — wipes every torrent, peer, and event). Off by default. */
$settings['db_reset'] = false;

////	Advanced Database Options
/* name prefixes for the Phoenix tables */
$settings['db_prefix'] = 'phoenix_';
/* use persistent connections if available. */
$settings['db_persist'] = true;

////	General Tracker Options
/* track anything announced to it; off = closed/private tracker (BEP 27) */
$settings['open_tracker'] = false;
/* how often client will send requests */
$settings['announce_rec_interval'] = 1800; // 30 minutes
/* how often client can force requests */
$settings['announce_min_interval'] = 60; // 1 minute
/* default # of peers to announce */
$settings['default_peers'] = 50;
/* max # of peers to announce */
$settings['max_peers'] = 100;

////	Advanced Tracker Options
/* let a client declare its own address via ?ip / ?ipv4 / ?ipv6 (for a NATed */
/* peer that knows its public IP). Lowest priority — behind REMOTE_ADDR and */
/* still subject to reject_private_ips — but a client can then claim ANY public */
/* address, so leave off unless you need it. NOT announce_external_ip, which */
/* instead echoes the client's address back to it (BEP 24). */
$settings['allow_client_ip'] = false;
/* default to compact announces when not specified */
$settings['default_compact'] = true;
/* echo the client's own public IP back in announce responses (BEP 24) */
$settings['announce_external_ip'] = true;
/* allow scrapes with no info_hash, which return EVERY torrent's stats. */
/* Conventional for open trackers. Set false on a closed/private tracker: */
/* a full scrape ignores the allowed-torrents filter, so leaving it on */
/* exposes your whole torrent list to anyone who scrapes. */
$settings['full_scrape'] = true;
/* minimum seconds between scrape requests, advertised to clients as BEP 48's */
/* `min_request_interval` in the scrape response. 0 = do not advertise it */
$settings['scrape_min_interval'] = 900; // 15 minutes
/* Throttle rapid fake-peer injection: reject an announce when this IP already */
/* has announce_rate_limit OTHER active peer_ids for the same torrent within */
/* announce_rate_window seconds. The check is keyed on IP alone, so co-located */
/* clients — a shared home NAT, or many unrelated subscribers behind one CGNAT */
/* IPv4 — count together. Keep it high enough for a busy household; on a tracker */
/* fronted by a proxy/CDN, prefer per-IP rate limiting there (see APACHE.md / */
/* NGINX.md) and set this to 0. 0 = disable the check entirely. */
$settings['announce_rate_limit'] = 10;
/* Window in seconds for announce_rate_limit. Independent of the announce */
/* cadence, so tuning announce_rec_interval never widens the anti-abuse window. */
$settings['announce_rate_window'] = 120; // 2 minutes
/* randomise peer selection to spread load */
$settings['random_peers'] = true;
/* minimum swarm size before random_peers randomises peer selection */
$settings['random_peers_threshold'] = 500;
/* tweaks % of time tracker attempts idle peer removal */
/* if you have a busy tracker, you may adjust this */
/* example: 1 = 1%, 10 = 10%, 50 = 50%, 100 = every time */
$settings['clean_request_percent'] = 1;
/* should your tracker clean with cron */
/* means clean_request_percent can be disabled for faster responses */
$settings['clean_with_cron'] = false;
/* days of maintenance-task run history to keep in the task_runs log; */
/* 0 = keep forever. pruned during the regular cleanup (announce or cron) */
$settings['task_retention'] = 0;
/* Which forwarded-address headers to trust, in priority order. Empty = trust */
/* none and use the direct connection (REMOTE_ADDR) only — the safe default. */
/* Only list headers your reverse proxy actually SETS and strips from client */
/* input, else you allow trivial IP spoofing. Recognised: x-forwarded-for, */
/* forwarded (RFC 7239), x-real-ip, cf-connecting-ip, true-client-ip, and the */
/* legacy client-ip. e.g. ['x-forwarded-for'] or ['cf-connecting-ip']. Often */
/* better to have the web server rewrite REMOTE_ADDR instead — see APACHE.md */
/* / NGINX.md. */
$settings['forwarded_headers'] = [];
/* CIDR ranges of trusted reverse proxies. A forwarded header is honored only */
/* when the direct connection (REMOTE_ADDR) falls inside one of these ranges, */
/* and chain headers (X-Forwarded-For / Forwarded) are walked from the right, */
/* skipping these ranges, to find the real client. See APACHE.md / NGINX.md. */
$settings['trusted_proxies'] = [];
/* Permit an EMPTY trusted_proxies to still trust forwarded headers — i.e. */
/* from ANY connecting peer. Insecure: anyone reaching the tracker directly */
/* can then spoof their address. Leave false unless you fully control who can */
/* connect and understand the risk. */
$settings['trust_any_forwarded'] = false;
/* drop private (RFC 1918) and reserved addresses when resolving peers, so they */
/* are never handed out to the swarm. Lets a private REMOTE_ADDR (NAT/proxy) */
/* fall through to a public client-declared IP (allow_client_ip), per BEP 3. A */
/* peer left with no routable */
/* address after this is rejected outright, not stored — so set false for LAN, */
/* same-NAT, or loopback trackers, where peers legitimately have private */
/* addresses (announcing from 127.0.0.1 needs this off). */
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
/* fire an 'error' hook (src/hooks/phoenix.error.php) for server-side */
/* failures + uncaught exceptions/fatals, so an external monitor such as */
/* Sentry can be wired up without touching core. off by default. */
$settings['report_errors'] = false;

////	Admin Options
/* bcrypt hash of the admin password. Empty makes admin.php force a one-time */
/* "set admin password" step on first access (see admin_auth_optional below). */
$settings['admin_password'] = '';
/* when admin_password is empty, run the panel with NO password instead of */
/* forcing the set-password step. Only enable if admin.php is protected by */
/* other means (reverse-proxy auth / IP allowlist) — better still, delete */
/* public/admin.php once you're up and running. */
$settings['admin_auth_optional'] = false;
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
/* API keys for the management REST API, keyed by user. Each value is the */
/* SHA-256 hash of the issued key — manage these on the admin panel's API Keys */
/* page, which shows a new key once and stores only its hash (a lost key can't */
/* be recovered from here — re-issue it). */
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
$settings['backup_retention'] = 30;
