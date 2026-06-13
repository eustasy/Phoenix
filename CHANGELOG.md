# Phoenix Changelog

## v4.1beta4 - 13/06/2026

* FEATURE: Add a tracker management API under `public/api/`, with one thin entry-point file per path (no action router) and authenticated by per-user API keys in the new `api_keys` setting (`'user' => 'key'` pairs). The first endpoint is `/api/torrent/add` (`public/api/torrent/add.php`), which adds a torrent — add-only, so an info_hash that is already tracked is an error — recording the key's user in a new `user` column on the torrents table. Parameters come from POST or GET; responds with JSON by default, XML with `?xml`. **DB schema modifications required.**
* FEATURE: Add `/api/torrents` (`public/api/torrents.php`) to the management API — a read endpoint listing every torrent (listed and unlisted, any owner) with swarm stats (seeders/leechers/peers/traffic) plus each torrent's `user` and `listed` flag. Any valid key sees the full list, so keys should only be issued to trusted operators (per-key scopes are a follow-up). JSON by default, XML with `?xml`.
* FEATURE: Add the management-API mutations `/api/torrent/list` and `/api/torrent/delist` (`public/api/torrent/{list,delist}.php`) to toggle a torrent's public-index visibility (`listed` 1/0). Authorization: an owner key may only touch its own torrents, while the reserved `'*'` admin owner — its API key, or a logged-in `admin.php` session carrying a CSRF token — may touch any torrent, including announce-created rows with no owner. A missing row and a not-owned row both report `Torrent not found.`, so ownership never discloses existence. JSON by default, XML with `?xml`.
* FEATURE: Add `/api/torrent/delete` (`public/api/torrent/delete.php`) to delete a torrent and its peer rows (so the swarm disappears at once). Same ownership/admin rules as list/delist, and gated by the new `api_allow_delete` setting (off by default; the `'*'` admin is always exempt). **Caveat:** on an open tracker a deleted torrent reappears on its next announce — deletion is only decisive on a closed tracker.
* FEATURE: Add the API discovery index `/api` (`public/api/index.php`), returning the running Phoenix version under a `phoenix` object. Unauthenticated (no torrent data, just a version signature). JSON by default, XML with `?xml`.
* FEATURE: Track torrent meta — `filename`, a structured `files` list, extra `trackers`, and `webseeds` — in four new nullable columns on the torrents table. Populated via the API add (explicit fields or a `.torrent` upload parsed server-side, capped by the new `torrent_upload_max` setting) and served on the public index JSON/XML when the new `index_show_meta` setting is on (default off; output unchanged). **DB schema modifications required.**
* FEATURE: Add a minimal migration mechanism: idempotent SQL files under `sql/migrations/` (importable manually with `mysql <database> < sql/migrations/<file>.sql`), runnable from a new admin-panel "Upgrade Schema" action.
* IMPROVES: Document the contribution conventions in `.github/CONTRIBUTING.md` and enforce the one-function-per-file rule from the test suite, splitting the multi-function files accordingly.
* FEATURE: The public index now serves a ready-made magnet link for every torrent in all three formats (HTML link column, JSON `magnet` field, XML `<magnet>` element), assembled from the stored info_hash/name/size, the new `announce_url` setting as the first `tr=` tier, and — only when `index_show_meta` is on — the stored trackers and webseeds.
* FEATURE: Opt-in stat-tracking: announce events (completions by default; optionally started/stopped via `stats_events`) are logged to a new `events` table with a coarse client label and an optional minified geo location (country/continent, via the suggested `geoip2/geoip2` library and an operator-supplied GeoLite2 database). Privacy-preserving by design — no IP, peer_id, or port is ever stored. The table is created at install; existing installs get it from the admin panel's Upgrade Schema action (which now also creates newly-added tables) or `mysql <database> < sql/events.sql`. Off by default via `stats_enabled`. The regular cleanup prunes events older than `stats_retention` days (0 = keep forever, the default).

### SQL Migration (from 4.0beta3 or 3.2)

Run the files in `sql/migrations/` (or use the admin panel's Upgrade Schema action), which apply:

```sql
ALTER TABLE `your_db`.`your_prefix_torrents`
    ADD COLUMN IF NOT EXISTS `user` varchar(255) NULL FIRST,
    ADD COLUMN IF NOT EXISTS `filename` varchar(255) NULL,
    ADD COLUMN IF NOT EXISTS `files` longtext NULL,
    ADD COLUMN IF NOT EXISTS `trackers` longtext NULL,
    ADD COLUMN IF NOT EXISTS `webseeds` longtext NULL;
```

## v4.0beta3 - 04/06/2026

The 4.0 line is a ground-up refactor of the 3.x codebase: a new on-disk layout, an MVC-inspired split of the old "puff" files, a full PHPUnit suite, CI with static analysis, and a security-review pass. The tracker protocol behaviour is unchanged. **Operators upgrading from 3.x should read the [3.x → 4.0 Migration Guide](MIGRATING.md) first** — the document root, configuration, and cron paths have all moved.

* BREAKING: Adopt a Public Document Standard layout — only `public/` is web-served, with `src/`, `bin/`, `config/`, and `tests/` above the web root ([#48](https://github.com/eustasy/phoenix/issues/48)). **The web server document root must be re-pointed at `public/`.** See [APACHE.md](APACHE.md) / [NGINX.md](NGINX.md).
* BREAKING: Maintenance scripts moved from `_cron/hourly/` to `bin/` (`bin/backup-database.php`, `bin/clean-and-optimize.php`). **Cron jobs must be updated.**
* BREAKING: User configuration moved from `_settings/phoenix.custom.php` to `config/phoenix.custom.php` (template: `config/phoenix.default.php`). Existing overrides are forward-compatible.
* BREAKING: Minimum PHP raised to 8.2, requiring the `mysqli` and `xml` extensions ([#41](https://github.com/eustasy/phoenix/issues/41)).
* FEATURE: Tables are now defined in standalone `sql/*.sql` files (importable with `mysql <database> < sql/peers.sql`), loaded automatically by `db_create()`. Same schema as 3.2 — **no DB migration is required.**
* FEATURE: Announce responses can be requested as JSON (`?json`) or XML (`?xml`), matching scrape's alternative formats.
* IMPROVES: Reorganise the "puff" files into an MVC-inspired split — thin `public/` entry points delegating to `src/controller/`, `src/model/`, `src/views/`, and `src/functions/`.
* IMPROVES: Funnel all bencode output through a single `bencode_encode()` emitter that owns length prefixes, container tokens, and BEP-3 dict-key ordering.
* IMPROVES: Replace the bespoke test harness with a PHPUnit suite, plus endpoint smoke tests, run in CI across PHP 8.2–8.6 against a MariaDB service container.
* IMPROVES: Add CI static analysis and formatting via qlty (phpstan + php-cs-fixer), sqlfluff, and markdownlint; configure Dependabot for Actions and Composer.
* IMPROVES: Apply `declare(strict_types=1)` across the project and add PHP type declarations throughout.
* IMPROVES: Add CSRF tokens to admin panel state-changing forms ([#59](https://github.com/eustasy/phoenix/issues/59)).
* IMPROVES: Add a brute-force throttle to admin login, harden the session cookie, regenerate the session id on login, and make logout POST-only.
* IMPROVES: Use prepared statements for client-data queries.
* IMPROVES: Tighten the `Access-Control-Allow-Origin` scope and route source-IP handling through `reject_private_ips`, parsing multi-hop `X-Forwarded-For` correctly.
* IMPROVES: Reject non-hex `info_hash` and `peer_id` values at the boundary.
* IMPROVES: Add an XML-escape helper for stats/scrape XML output; pin `normalize.css` and `Colors.css` with SRI integrity and drop the jQuery dependency from the admin panel.
* IMPROVES: Send a `Content-Type` header on bencode announce and scrape responses, and content-negotiate `tracker_error` output.
* BUGFIX: Filter rejected `info_hash`es out of multi-torrent scrape so a closed tracker never leaks disallowed torrents ([#49](https://github.com/eustasy/phoenix/issues/49)).
* BUGFIX: Wrap the XML scrape response in a `<scrape>` root element.
* BUGFIX: Default the `ipv4`/`ipv6` peer columns to an empty-string sentinel rather than `'0'`.
* BUGFIX: Skip bare query-string keys in `sanitize_tracker_params`, and guard announce input validation against a `false` sanitiser result.
* BUGFIX: Consume the first `multi_query` result in `task_optimize`, and guard the admin MySQL-version `substr` against `strpos` returning `false`.

### Database Migration

None. 4.0 uses the same schema as 3.2. If upgrading from 3.1 or earlier, apply the **v.3.2** SQL migration below first; from 3.2 there are no database changes. See the [Migration Guide](MIGRATING.md) for full upgrade steps.

## v.3.2 - 09/05/2026 - Haggard

* BREAKING: Requires at least PHP 7.1
* BREAKING: Replace backup bash script with PHP; add backup_rotate setting. **Cron jobs will require changing and testing.**
* BREAKING: Track size of downloads ([#34](https://github.com/eustasy/phoenix/issues/34)). **DB schema modifications required.**
* FEATURE: Allow the admin script to create a configuration file ([#31](https://github.com/eustasy/phoenix/issues/31)).
* FEATURE: Add an Index of publicly listed torrents ([#32](https://github.com/eustasy/phoenix/issues/32)).
* FEATURE: Add a magnet link generator ([#33](https://github.com/eustasy/phoenix/issues/33)).
* IMPROVES: Add authentication to the administration area ([#4](https://github.com/eustasy/phoenix/issues/4)).
* IMPROVES: Add comments to all remaining uncommented files ([#35](https://github.com/eustasy/phoenix/issues/35)).
* IMPROVES: Be more helpful when listing peers ([#36](https://github.com/eustasy/phoenix/issues/36)).
* IMPROVES: Protect against spamming fake peers ([#37](https://github.com/eustasy/phoenix/issues/37)).
* IMPROVES: Exclude peers table from database backup ([#43](https://github.com/eustasy/phoenix/issues/43)).
* IMPROVES: Use `--defaults-extra-file` to avoid exposing DB password in process list.
* IMPROVES: Better filtering of non-hex info_hash and peer_id values.
* IMPROVES: Add most PHP typing.
* BUGFIX: Incorrect uppercase in `tracker_error` - BEP madates lowercase.
* BUGFIX: Sort non-compact bencode peer-dict keys per BEP 3.
* BUGFIX: Fix parse_ipv4 IPv4-mapped IPv6 prefix stripping.
* BUGFIX: Remove double decoding in `once.sanitize.tracker.php`.
* BUGFIX: Unclosed integer in non-compact bencode.
* BUGFIX: Several spelling errors.
* BUGFIX: Honor external_ip setting in address sanitization.
* BUGFIX: Honor random_peers and random_limit settings in peer selection.
* BUGFIX: Filter all torrents on scrape with `tracker_filter_info_hashes`

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

## v.3.1 - 14/04/2016 - Unicorn

* IMPROVES: Switched to MIT Licensing.
* IMPROVES: Documented how to set up cron jobs ([#28](https://github.com/eustasy/phoenix/issues/28)).
* IMPROVES: Documented where custom configuration _should_ be stored.
* IMPROVES: De-duplicate test initialization and database initialization ([#27](https://github.com/eustasy/phoenix/issues/27)).
* IMPROVES: Automated testing now uses the same database, and respects prefixes, and custom usernames and database names.
* IMPROVES: Update default settings to not allow database resets.
* IMPROVES: Some tests are now stricter.
* BUGFIX: Fixes table-size erroring when no tables are deployed.

## v.3.0 - 21/01/2016 - Sanitized

* FEATURE: Adds multi-torrent scraping ([#19](https://github.com/eustasy/phoenix/issues/19)).
* FEATURE: Optionally replace tasks with cron jobs ([#22](https://github.com/eustasy/phoenix/issues/22)).
* FEATURE: Adds unit-tests for functions ([#17](https://github.com/eustasy/phoenix/issues/17)).
* FEATURE: Adds normalized checks.
* IMPROVES: Sanitizes super-globals before use ([#16](https://github.com/eustasy/phoenix/issues/16)).
* IMPROVES: Documented hook files ([#23](https://github.com/eustasy/phoenix/issues/23)).
* IMPROVES: Removes duplicated variables ([#20](https://github.com/eustasy/phoenix/issues/20)).
* IMPROVES: Moved to puff-style structure ([#18](https://github.com/eustasy/phoenix/issues/18)).
* IMPROVES: IP detection, especially when reporting IPv4 _and_ IPv6.
* IMPROVES: Adds database size to `admin.php`
* BUGFIX: Variables should be named in international english ([#21](https://github.com/eustasy/phoenix/issues/21)).
* BUGFIX: Fix compact reporting for IPv4.
* BUGFIX: Fix full-tracker scraping.
* REMOVES: Nothing.

## v.2.0 - 20/08/2015 - Unification

* FEATURE: Adds support for IPv6 ([#3](https://github.com/eustasy/phoenix/issues/3)).
* IMPROVES: More tasks are logged.
* BUGFIX: Task names being trimmed.
* BUGFIX: Task being duplicated.
* BUGFIX: Certain torrents binary hash is malformed due to a poorly implemented "verbose" mode ([#14](https://github.com/eustasy/phoenix/issues/14)).
* REMOVES: Verbose mode for torrent scraping. JSON and XML are still available.

## v.1.4 - 18/08/2015 - Totalitarian

* FEATURE: Add downloads totals ([#10](https://github.com/eustasy/phoenix/issues/10)).
* FEATURE: Add preliminary support for IPv6 ([#3](https://github.com/eustasy/phoenix/issues/3)).
* IMPROVES: Git ignores hooks or custom files.
* IMPROVES: Adds verbose option to torrent scraping for better display of bencoded content.
* BUGFIX: Fixes scrape counts of torrents by encoding hashes in their binary format.
* BUGFIX: Fixes issue where cleaning was never logged.

## v.1.3 - 16/02/2015 - Hexa

* BUGFIX: Fixes issue with escaping binary data by storing it all as Hexadecimal.

## v.1.2 - 31/12/2014 - Endpoints

* FEATURE: Support Endpoints, rather than just separate ports.

## v.1.1 - 31/12/2014 - Scraping By

* FEATURE: Adds JSON and XML output to scrapes and stats.
* FEATURE: Adds HEX info_hash support to announce.
* IMPROVES: Stop double-submissions on admin page.
* IMPROVES: Improves configuration defaults.
* BUGFIX: Fix broken scraping when requesting a torrent as a binary value.
* BUGFIX: Set correct default charset.

## v.1.0 - 28/12/2014 - No longer PeerTracker

* A procedural re-write of PeerTracker in a modern format.
* Fixes numerous bugs and massively improves performance, modularity, and maintainability.

*****

## PeerTracker Changelog

### v0.1.3 - 01/20/2010

* BUGFIX: Failure to assign returned data from stripslashes.

### v0.1.2 - 11/18/2009

* BUGFIX: Garbage collection routine interval.

### v0.1.1 - 10/31/2009

* FEATURE: Tracker Statistics (peers, seeders, leechers, torrents) output via html, xml & json.
* FEATURE: Database Prefixes, allows multiple trackers to be ran from a single database.
* FEATURE: Support for persistent connections (via mysql or mysqli (php >= 5.3)).
* IMPROVES: Implemented support for full scrapes.
* IMPROVES: More efficient table rows.

### v0.1.0 - 10/24/2009

* FEATURE: Completed /announce and partial /scrape support.
