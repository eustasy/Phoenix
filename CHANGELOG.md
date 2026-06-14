# Phoenix Changelog

## v4.2beta5 - 14/06/2026

The 4.2 line expands the admin panel into a full web management UI on top of 4.1's management API, adds a torrent-update endpoint, and ships a disposable Docker development environment. The tracker protocol and database schema are unchanged — **no DB migration is required.**

* FEATURE: A web admin panel built on a shared layout, top navigation, and a light `?page=` router, with every state-changing form CSRF-protected and the login behind a hardened session and a failed-login throttle ([#54](https://github.com/eustasy/phoenix/issues/54)). The nav covers a Dashboard, Torrents, Add Torrent, Server Support (PHP/MySQL/extension diagnostics), Utilities (database setup/reset, clean, optimize, schema migrate), Backups, and Settings — each page below hangs off it.
* FEATURE: A **Dashboard** showing live tracker stats — seeders/leechers/peers, registered torrents, completed downloads, and traffic — plus the last-run time of each maintenance task and a post-install confirmation banner ([#55](https://github.com/eustasy/phoenix/issues/55)).
* FEATURE: A **Torrents** page listing every torrent (listed or not, any owner) with swarm stats, and per-row actions to list/de-list, delete (with its peers), edit, or drill into its peers ([#56](https://github.com/eustasy/phoenix/issues/56)).
* FEATURE: A per-torrent **peer drill-down** — client (detected from the peer_id for display, never stored), address, seeding/leeching state, up/down/left, and last-seen time. It also surfaces _unregistered swarms_: info_hashes with active peers but no torrents row (e.g. announced to an open tracker and never registered), so their peers are still counted and inspectable.
* FEATURE: An **Add a Torrent** page accepting the fields manually or a `.torrent` upload (drag-and-drop or file picker, parsed server-side); admin-added torrents record no API owner ([#66](https://github.com/eustasy/phoenix/issues/66)).
* FEATURE: A **Backups** page to run an on-demand database dump and download an existing one. The download name is validated against the backup list, so there is no path traversal; the action needs `mysqldump`, `proc_open`, and a writable backup directory ([#57](https://github.com/eustasy/phoenix/issues/57)).
* FEATURE: A **Settings** page — a read-only view of the effective settings (secrets masked), an admin-password change, and toggles for the `open_tracker` / `public_index` / `full_scrape` / `db_reset` flags — all gated on a writable `config/` directory ([#58](https://github.com/eustasy/phoenix/issues/58)).
* FEATURE: Enable or disable the admin TOTP second factor from the Settings page after install (4.1 only offered enrolment at install time). Enabling shows a fresh QR and requires a confirming code; disabling requires a current code, so a hijacked-but-authenticated session cannot silently turn 2FA off.
* FEATURE: Add `/api/torrent/update` (`public/api/torrent/update.php`) to edit an existing torrent's name, size, `listed`, and meta fields, with a matching admin **Edit** page. Same ownership rules as list/delist/delete — an owner key edits only its own torrents, the `'*'` admin (key or `admin.php` session) edits any. The API does a partial update (only the fields you send change; info_hash, owner, and the downloads counter are immutable); the admin form does a full replace. JSON by default, XML with `?xml`.
* FEATURE: A disposable Docker development environment (`docker-compose.dev.yml` + `docker/`) — MariaDB plus PHP with the needed extensions, Composer, and a MariaDB-compatible `mysqldump` — that boots straight into the installer for testing setup and configuration end to end, without touching the host checkout.
* IMPROVES: Reorganise the documentation along development / installation / deployment lines. The README is now installation-focused, with a table of contents, a single requirements list, two install paths (server access vs managed LAMP / shared hosting), and an index of the other docs; the local Docker environment and test instructions moved to `.github/CONTRIBUTING.md`; the Apache/Nginx example configs fold in the safely-assumable features (`.php` stripping, `Authorization` passthrough, HTTPS redirect, dotfile denial); and admin-password and 2FA recovery are now documented.
* FIX: Serve the admin panel as UTF-8. It was inheriting the `iso-8859-1` charset the bootstrap sets for the binary tracker protocol, which mangled non-ASCII torrent names and the em-dash in the panel.
* FIX: Two-factor enrolment QR — drop a doubled `data:image/png;base64,` prefix that left the image broken, and guard `generateQrCode()` behind `ext-gd` so an install that has the authenticatron library but not gd falls back to the manual secret / `otpauth://` entry instead of fatalling (in both the installer and the Settings page).
* FIX: Adding a torrent from a `.torrent` upload no longer fails when the form's other fields are left blank — a blank field falls back to the value parsed from the file instead of overwriting it.

## v4.1beta4 - 13/06/2026

* FEATURE: Add a tracker management API under `public/api/`, with one thin entry-point file per path (no action router) and authenticated by per-user API keys in the new `api_keys` setting (`'user' => 'key'` pairs), sent as an `Authorization: Bearer <key>` header (keys never ride query strings). The first endpoint is `/api/torrent/add` (`public/api/torrent/add.php`), which adds a torrent — POST only, add-only (an already-tracked info_hash is an error) — recording the key's user in a new `user` column on the torrents table. Responds with JSON by default, XML with `?xml`. **DB schema modifications required.**
* FEATURE: Add `/api/torrents` (`public/api/torrents.php`) to the management API — a GET read endpoint listing torrents with swarm stats (seeders/leechers/peers/traffic) plus each torrent's `user` and `listed` flag. Scoped: a normal key sees only its own torrents, while the `'*'` admin (its key, or a logged-in `admin.php` session) sees every torrent, listed and unlisted, any owner. JSON by default, XML with `?xml`.
* FEATURE: Add the management-API mutations `/api/torrent/list` and `/api/torrent/delist` (`public/api/torrent/{list,delist}.php`) to toggle a torrent's public-index visibility (`listed` 1/0). POST only. Authorization: an owner key (via the `Authorization: Bearer` header) may only touch its own torrents, while the reserved `'*'` admin owner — its API key, or a logged-in `admin.php` session carrying a CSRF token — may touch any torrent, including announce-created rows with no owner. A missing row and a not-owned row both report `Torrent not found.`, so ownership never discloses existence. JSON by default, XML with `?xml`.
* FEATURE: Add `/api/torrent/delete` (`public/api/torrent/delete.php`) to delete a torrent and its peer rows (so the swarm disappears at once). POST only, same Bearer-header / admin-session ownership rules as list/delist, and gated by the new `api_allow_delete` setting (off by default; the `'*'` admin is always exempt). **Caveat:** on an open tracker a deleted torrent reappears on its next announce — deletion is only decisive on a closed tracker.
* FEATURE: Add the API discovery index `/api` (`public/api/index.php`), returning the running Phoenix version under a `phoenix` object. Unauthenticated (no torrent data, just a version signature). JSON by default, XML with `?xml`.
* FEATURE: Track torrent meta — `filename`, a structured `files` list, extra `trackers`, and `webseeds` — in four new nullable columns on the torrents table. Populated via the API add (explicit fields or a `.torrent` upload parsed server-side, capped by the new `torrent_upload_max` setting) and served on the public index JSON/XML when the new `index_show_meta` setting is on (default off; output unchanged). **DB schema modifications required.**
* FEATURE: Add a minimal migration mechanism: idempotent SQL files under `sql/migrations/` (importable manually with `mysql <database> < sql/migrations/<file>.sql`), runnable from a new admin-panel "Upgrade Schema" action.
* IMPROVES: Document the contribution conventions in `.github/CONTRIBUTING.md` and enforce the one-function-per-file rule from the test suite, splitting the multi-function files accordingly.
* FEATURE: The public index now serves a ready-made magnet link for every torrent in all three formats (HTML link column, JSON `magnet` field, XML `<magnet>` element), assembled from the stored info_hash/name/size, the new `announce_url` setting as the first `tr=` tier, and — only when `index_show_meta` is on — the stored trackers and webseeds.
* FEATURE: Opt-in stat-tracking: announce events (completions by default; optionally started/stopped via `stats_events`) are logged to a new `events` table with a coarse client label and an optional minified geo location (country/continent, via the suggested `geoip2/geoip2` library and an operator-supplied GeoLite2 database). Privacy-preserving by design — no IP, peer_id, or port is ever stored. The table is created at install; existing installs get it from the admin panel's Upgrade Schema action (which now also creates newly-added tables) or `mysql <database> < sql/events.sql`. Off by default via `stats_enabled`. The regular cleanup prunes events older than `stats_retention` days (0 = keep forever, the default).
* FEATURE: Optional TOTP two-factor authentication on the admin panel, via the suggested `eustasy/authenticatron` library (the QR code needs PHP's `gd` extension; without it the installer shows the secret + `otpauth://` URL for manual entry). Enrolment is offered during install and is skippable — scan the code and enter a current code to confirm (proving the authenticator works before the secret is stored), or leave it blank to stay password-only. When enabled, login requires a 6-digit code alongside the password, verified independently and fail-closed: a configured secret with the library missing denies login rather than silently downgrading. Stored as the new `admin_totp_secret` setting; recover by removing that line from `phoenix.custom.php`.

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
