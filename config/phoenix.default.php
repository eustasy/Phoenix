<?php

////	DO NOT MODIFY `phoenix.default.php`
// Copy to `phoenix.custom.php` and modify there.

////	General Database Options
$settings['db_host'] = '%db_host%';           /* ip or hostname to mysql server */
$settings['db_user'] = '%db_user%';           /* username used to connect to mysql */
$settings['db_pass'] = '%db_pass%';           /* password used to connect to mysql */
$settings['db_name'] = '%db_name%';           /* name of the Phoenix database */
$settings['db_reset'] = false;                /* allow database to be reset in admin */

////	Advanced Database Options
$settings['db_prefix'] = '%db_prefix%';       /* name prefixes for the Phoenix tables */
$settings['db_persist'] = '%db_persist%';     /* use persistent connections if available. */

////	General Tracker Options
$settings['open_tracker'] = '%open_tracker%'; /* track anything announced to it */
$settings['announce_interval'] = 1800;        /* how often client will send requests */
$settings['min_interval'] = 600;              /* how often client can force requests */
$settings['default_peers'] = 50;              /* default # of peers to announce */
$settings['max_peers'] = 100;                 /* max # of peers to announce */

////	Advanced Tracker Options
$settings['external_ip'] = false;             /* allow client to specify ip address */
$settings['default_compact'] = true;          /* force compact announces only */
$settings['full_scrape'] = true;              /* allow scrapes without info_hash */
$settings['random_peers'] = true;             /* randomise peer selection to spread load */
$settings['random_limit'] = 500;              /* minimum swarm size before RAND() is used */
$settings['clean_with_requests'] = 1;         /* tweaks % of time tracker attempts idle peer removal */
                                              /* if you have a busy tracker, you may adjust this */
                                              /* example: 1 = 1%, 10 = 10%, 50 = 50%, 100 = every time */
$settings['clean_with_cron'] = false;         /* should your tracker clean with cron */
                                              /* means clean_with_requests can be disabled for faster responses */
$settings['honor_xff'] = false;               /* If this server is behind a frontend proxy, the client IP */
                                              /* will come in the form of a X-Forwarded-For. This option */
                                              /* should only be set if your frontend proxy properly handles */
                                              /* and filters XFF, else it allows for trivial IP spoofing */
$settings['reject_private_ips'] = true;       /* drop private (RFC 1918) and reserved addresses when */
                                              /* resolving peers, so they are never handed out to the swarm. */
                                              /* Lets a private REMOTE_ADDR (NAT/proxy) fall through to a */
                                              /* public external_ip, per BEP 3. Set false for LAN/same-NAT */
                                              /* trackers, where peers legitimately have private addresses. */
$settings['public_index'] = false;            /* serve a public index of listed torrents */

////	Logging & Debugging
$settings['error_log'] = '';                  /* absolute path for PHP's error log; empty = server/PHP default */
$settings['debug'] = false;                   /* surface + verbosely log errors for local troubleshooting. */
                                              /* NEVER enable in production: display_errors corrupts bencode */
                                              /* responses and discloses internals. Errors are always logged. */

////	Admin Options
$settings['admin_password'] = '';             /* bcrypt hash of the admin password; empty = no auth */
                                              /* WARNING: set this or delete public/admin.php when you're up and running */
$settings['admin_login_delay'] = 2;           /* seconds to delay after a failed admin login (brute-force throttle); 0 disables */
$settings['admin_login_delay_max'] = 8;       /* cap on the escalating per-session login delay */

////	Backup Options
$settings['backup_dir'] = '';                 /* absolute path to backup directory; empty = backups/ in the project root */
$settings['backup_rotate'] = 30;              /* delete backups older than this many days */
