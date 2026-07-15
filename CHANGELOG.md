# Phoenix Changelog

## v3.2.2 - 15/07/2026 - Haggard

* BUGFIX: Full and multi-hash scrapes emitted malformed bencode whenever more or fewer than exactly one torrent matched; all torrents now share a single sorted `files` dictionary.
* BUGFIX: XML scrapes had no root element and JSON scrapes returned `null` for an empty result set.
* BUGFIX: The clean task could delete real torrents whose name matched the unescaped test-residue pattern (e.g. "untested"); the purge now only runs against `TESTING_`-prefixed tables and escapes its `LIKE` wildcards.
* BUGFIX: Announces without `left`, or with an out-of-range port, failed against strict-mode MySQL/MariaDB (the default since MySQL 5.7 / MariaDB 10.2.4). The `left` sentinel is clamped at the SQL boundary and ports are validated to 1–65535.
* BUGFIX: Duplicate `completed` announces (Transmission sends every announce twice) double-counted downloads; the counter and `download.complete` hook now only fire when the peer was not already seeding.
* BUGFIX: On PHP 8.1+ any database failure surfaced as an uncaught `mysqli_sql_exception` (HTTP 500) instead of a bencoded tracker error; mysqli error reporting is pinned to return-value mode everywhere.
* BUGFIX: The connection-failure diagnostic never worked (`mysqli_connect_error()` takes no arguments — fatal on PHP 8.0); the driver detail now goes to the server error log while clients receive a generic failure.
* BUGFIX: The `tracker_error` test relied on overriding the exit code from a shutdown function, which PHP 7.x ignores for scripts run from a file; it now checks a child process, and the test runner's aggregate exit code finally works.
* IMPROVES: The backup script creates its backup directory on first run (mode `0700`) and reports unwritable or uncreatable directories with clear messages and a non-zero exit for cron to capture.
* IMPROVES: Document Nginx and Apache rules denying `.` and `_` prefixed paths, so settings, backups, tests, and internals cannot be fetched over HTTP ([README](README.md#securing-the-web-root)).
* IMPROVES: The install guide now leads with the `admin.php` installer; hand-written configs get an explicit warning that unreplaced `%placeholder%` values are truthy (a leftover `%open_tracker%` silently opened the tracker).
* IMPROVES: Document admin authentication: bcrypt hash storage, empty password meaning no authentication, and how to reset or recover the password.

## v3.2.1 - 07/07/2026 - Haggard

- BUGFIX: Restore PHP 7.1 compatibility — 3.2 unintentionally required PHP 8.1+ via `never`/`true` return types, union types, and `str_starts_with()`.
- BUGFIX: Only delete a peer and fire the `stopped` hook when that peer was actually being tracked.
- BUGFIX: Normalize uppercase `info_hash` and `peer_id` to lowercase so a torrent is not split across two swarms.
- IMPROVES: Update README backup and cron instructions to match the PHP backup script.
- IMPROVES: Correct `parse_ipv4` and `peer_format_bencode` unit tests to match intended behavior.

## v3.2 - 09/05/2026 - Haggard

- BREAKING: Requires at least PHP 7.1
- BREAKING: Replace backup bash script with PHP; add backup_rotate setting. **Cron jobs will require changing and testing.**
- BREAKING: Track size of downloads ([#34](https://github.com/eustasy/phoenix/issues/34)). **DB schema modifications required.**
- FEATURE: Allow the admin script to create a configuration file ([#31](https://github.com/eustasy/phoenix/issues/31)).
- FEATURE: Add an Index of publicly listed torrents ([#32](https://github.com/eustasy/phoenix/issues/32)).
- FEATURE: Add a magnet link generator ([#33](https://github.com/eustasy/phoenix/issues/33)).
- IMPROVES: Add authentication to the administration area ([#4](https://github.com/eustasy/phoenix/issues/4)).
- IMPROVES: Add comments to all remaining uncommented files ([#35](https://github.com/eustasy/phoenix/issues/35)).
- IMPROVES: Be more helpful when listing peers ([#36](https://github.com/eustasy/phoenix/issues/36)).
- IMPROVES: Protect against spamming fake peers ([#37](https://github.com/eustasy/phoenix/issues/37)).
- IMPROVES: Exclude peers table from database backup ([#43](https://github.com/eustasy/phoenix/issues/43)).
- IMPROVES: Use `--defaults-extra-file` to avoid exposing DB password in process list.
- IMPROVES: Better filtering of non-hex info_hash and peer_id values.
- IMPROVES: Add most PHP typing.
- BUGFIX: Incorrect uppercase in `tracker_error` - BEP madates lowercase.
- BUGFIX: Sort non-compact bencode peer-dict keys per BEP 3.
- BUGFIX: Fix parse_ipv4 IPv4-mapped IPv6 prefix stripping.
- BUGFIX: Remove double decoding in `once.sanitize.tracker.php`.
- BUGFIX: Unclosed integer in non-compact bencode.
- BUGFIX: Several spelling errors.
- BUGFIX: Honor external_ip setting in address sanitization.
- BUGFIX: Honor random_peers and random_limit settings in peer selection.
- BUGFIX: Filter all torrents on scrape with `tracker_filter_info_hashes`

### SQL Migration

```sql
ALTER TABLE `your_db`.`your_prefix_peers`
    MODIFY `ipv4` char(15) NOT NULL DEFAULT '',
    MODIFY `ipv6` char(39) NOT NULL DEFAULT '',
    MODIFY `left` bigint(20) unsigned NOT NULL DEFAULT '0',
    ADD COLUMN `uploaded` bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `portv6`,
    ADD COLUMN `downloaded` bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `uploaded`;

ALTER TABLE `your_db`.`your_prefix_torrents`
    ADD COLUMN `size` bigint(20) unsigned NULL AFTER `info_hash`,
    ADD COLUMN `listed` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `size`;
```

### Cron (Scheduled Task) Migration

The new backup command is `php ~/phoenix/_cron/hourly/backup-database.php`

### Configuration Migration

Your custom config will continue to work, but do take a look at the new config options in [`_settings/phoenix.default.php`](_settings/phoenix.default.php)

## v3.1 - 14/04/2016 - Unicorn

- IMPROVES: Switched to MIT Licensing.
- IMPROVES: Documented how to set up cron jobs ([#28](https://github.com/eustasy/phoenix/issues/28)).
- IMPROVES: Documented where custom configuration _should_ be stored.
- IMPROVES: De-duplicate test initialization and database initialization ([#27](https://github.com/eustasy/phoenix/issues/27)).
- IMPROVES: Automated testing now uses the same database, and respects prefixes, and custom usernames and database names.
- IMPROVES: Update default settings to not allow database resets.
- IMPROVES: Some tests are now stricter.
- BUGFIX: Fixes table-size erroring when no tables are deployed.

## v3.0 - 21/01/2016 - Sanitized

- FEATURE: Adds multi-torrent scraping ([#19](https://github.com/eustasy/phoenix/issues/19)).
- FEATURE: Optionally replace tasks with cron jobs ([#22](https://github.com/eustasy/phoenix/issues/22)).
- FEATURE: Adds unit-tests for functions ([#17](https://github.com/eustasy/phoenix/issues/17)).
- FEATURE: Adds normalized checks.
- IMPROVES: Sanitizes super-globals before use ([#16](https://github.com/eustasy/phoenix/issues/16)).
- IMPROVES: Documented hook files ([#23](https://github.com/eustasy/phoenix/issues/23)).
- IMPROVES: Removes duplicated variables ([#20](https://github.com/eustasy/phoenix/issues/20)).
- IMPROVES: Moved to puff-style structure ([#18](https://github.com/eustasy/phoenix/issues/18)).
- IMPROVES: IP detection, especially when reporting IPv4 _and_ IPv6.
- IMPROVES: Adds database size to `admin.php`
- BUGFIX: Variables should be named in international english ([#21](https://github.com/eustasy/phoenix/issues/21)).
- BUGFIX: Fix compact reporting for IPv4.
- BUGFIX: Fix full-tracker scraping.
- REMOVES: Nothing.

## v2.0 - 20/08/2015 - Unification

- FEATURE: Adds support for IPv6 ([#3](https://github.com/eustasy/phoenix/issues/3)).
- IMPROVES: More tasks are logged.
- BUGFIX: Task names being trimmed.
- BUGFIX: Task being duplicated.
- BUGFIX: Certain torrents binary hash is malformed due to a poorly implemented "verbose" mode ([#14](https://github.com/eustasy/phoenix/issues/14)).
- REMOVES: Verbose mode for torrent scraping. JSON and XML are still available.

## v1.4 - 18/08/2015 - Totalitarian

- FEATURE: Add downloads totals ([#10](https://github.com/eustasy/phoenix/issues/10)).
- FEATURE: Add preliminary support for IPv6 ([#3](https://github.com/eustasy/phoenix/issues/3)).
- IMPROVES: Git ignores hooks or custom files.
- IMPROVES: Adds verbose option to torrent scraping for better display of bencoded content.
- BUGFIX: Fixes scrape counts of torrents by encoding hashes in their binary format.
- BUGFIX: Fixes issue where cleaning was never logged.

## v1.3 - 16/02/2015 - Hexa

- BUGFIX: Fixes issue with escaping binary data by storing it all as Hexadecimal.

## v1.2 - 31/12/2014 - Endpoints

- FEATURE: Support Endpoints, rather than just separate ports.

## v1.1 - 31/12/2014 - Scraping By

- FEATURE: Adds JSON and XML output to scrapes and stats.
- FEATURE: Adds HEX info_hash support to announce.
- IMPROVES: Stop double-submissions on admin page.
- IMPROVES: Improves configuration defaults.
- BUGFIX: Fix broken scraping when requesting a torrent as a binary value.
- BUGFIX: Set correct default charset.

## v1.0 - 28/12/2014 - No longer PeerTracker

- A procedural re-write of PeerTracker in a modern format.
- Fixes numerous bugs and massively improves performance, modularity, and maintainability.

*****

## PeerTracker Changelog

### v0.1.3 - 01/20/2010

- BUGFIX: Failure to assign returned data from stripslashes.

### v0.1.2 - 11/18/2009

- BUGFIX: Garbage collection routine interval.

### v0.1.1 - 10/31/2009

- FEATURE: Tracker Statistics (peers, seeders, leechers, torrents) output via html, xml & json.
- FEATURE: Database Prefixes, allows multiple trackers to be ran from a single database.
- FEATURE: Support for persistent connections (via mysql or mysqli (php >= 5.3)).
- IMPROVES: Implemented support for full scrapes.
- IMPROVES: More efficient table rows.

### v0.1.0 - 10/24/2009

- FEATURE: Completed /announce and partial /scrape support.
