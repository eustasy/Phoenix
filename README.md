# Phoenix v4.2beta5

[![Normal (PHP)](https://github.com/eustasy/Phoenix/actions/workflows/php.yml/badge.svg)](https://github.com/eustasy/Phoenix/actions/workflows/php.yml)
[![Test (PHP)](https://github.com/eustasy/Phoenix/actions/workflows/test-php.yml/badge.svg)](https://github.com/eustasy/Phoenix/actions/workflows/test-php.yml)
[![Smoke (PHP)](https://github.com/eustasy/Phoenix/actions/workflows/smoke-php.yml/badge.svg)](https://github.com/eustasy/Phoenix/actions/workflows/smoke-php.yml)
[![Normal (SQL)](https://github.com/eustasy/Phoenix/actions/workflows/sql.yml/badge.svg)](https://github.com/eustasy/Phoenix/actions/workflows/sql.yml)
[![Maintainability](https://qlty.sh/gh/eustasy/projects/Phoenix/maintainability.svg)](https://qlty.sh/gh/eustasy/projects/Phoenix)
[![Code Coverage](https://qlty.sh/gh/eustasy/projects/Phoenix/coverage.svg)](https://qlty.sh/gh/eustasy/projects/Phoenix)

A lightweight BitTorrent Tracker written in PHP, with an SQL backend, for people that just want to host a tracker — with an optional public index, stats, and admin panel, but not a full torrent listing site.

## Table of Contents

- [Installation](#installation)
- [Project Structure](#project-structure)
- [Configuration](#configuration)
- [Server Configuration](#server-configuration)
- [Cron](#cron-automating-maintenance)
- [Documentation](#documentation)

## Installation

> **Upgrading from 3.x?** The document root, configuration, and cron paths have all moved in 4.0. Follow the [3.x → 4.0 Migration Guide](./MIGRATING.md) before deploying. See [CHANGELOG.md](./CHANGELOG.md) for the full list of changes.

### Requirements

- PHP 8.2+ (the latest supported release recommended) with the `mysqli` extension. The bundled `filter`, `json`, `session`, `xml`, `pcre`, and `date` extensions are also used and enabled by default.
- A MySQL-compatible database — MariaDB recommended.
- Apache 2.4 or Nginx 1.18+.

### With server access

Use this path when you control the web server configuration (VPS, dedicated server, etc.).

1. Upload Phoenix to your server.
2. Point your web server's document root at the `public/` directory. Only `public/` should be web-reachable; `src/`, `bin/`, `config/`, and `tests/` must remain outside the document root so configuration (including database credentials) is never served. See [APACHE.md](./APACHE.md) or [NGINX.md](./NGINX.md) for vhost examples and `.php` extension-stripping rules.
3. Load `public/admin.php` in your browser and run **Setup** (this creates the database tables and writes `config/phoenix.custom.php`).
4. After setup, secure `admin.php` — the simplest approach is to remove it from `public/` (`mv public/admin.php src/admin.php`). Move it back temporarily if you ever need to re-run setup. Alternatively, rate-limit the endpoint; see [APACHE.md](./APACHE.md) or [NGINX.md](./NGINX.md).

### Managed LAMP / shared hosting

Use this path when you use a cPanel-style host with no direct web server configuration access.

1. Upload Phoenix to your server.
2. Set the site's document root to `public/` via your hosting control panel where possible. If the panel does not allow changing the document root, put the contents of `public/` in the web root and keep `src/`, `config/`, `bin/`, and `tests/` outside (above) it.
3. Apache hosts: add the `.php`-stripping rewrite rules from [APACHE.md](./APACHE.md) to `public/.htaccess` so clients can reach `/announce` without the `.php` suffix.
4. Create the database in your hosting control panel.
5. Set up the tracker — choose one:
    - Load `public/admin.php` in your browser and run **Setup**. It will create the tables and write `config/phoenix.custom.php` with your database credentials.
    - Or import the schema files manually (`sql/peers.sql`, `sql/torrents.sql`, `sql/tasks.sql`, `sql/events.sql`), copy `config/phoenix.default.php` to `config/phoenix.custom.php`, and fill in your database credentials.
6. After setup, secure `admin.php` as described above.

## Project Structure

Phoenix follows an MVC-inspired structure optimized for procedural PHP:

```text
phoenix/
├── public/              # Web-accessible entry points (thin; delegate to src/controller/)
│   ├── announce.php     # BitTorrent announce endpoint (BEP 3)
│   ├── scrape.php       # BitTorrent scrape endpoint (BEP 48)
│   ├── index.php        # Public torrent listing (optional)
│   ├── api/             # Management API (Authorization: Bearer <key>; index is public)
│   │   ├── index.php    # GET  /api — Phoenix version (no auth)
│   │   ├── torrents.php # GET  /api/torrents — your torrents + swarm stats (all, for admin)
│   │   └── torrent/
│   │       ├── add.php    # POST /api/torrent/add — add a torrent
│   │       ├── update.php # POST /api/torrent/update — edit a torrent's fields
│   │       ├── list.php   # POST /api/torrent/list — show on the index
│   │       ├── delist.php # POST /api/torrent/delist — hide from the index
│   │       └── delete.php # POST /api/torrent/delete — delete (+ its peers)
│   ├── admin.php        # Admin panel & installer
│   └── magnet.php       # Client-side magnet link generator
├── src/
│   ├── phoenix.php      # Bootstrap: loads config, connects to DB
│   ├── controller/      # Request handlers, one per endpoint/action (*_controller())
│   ├── functions/       # Business logic helpers (one function per file)
│   ├── model/           # Database operations (one query function per file)
│   ├── views/           # Presentation layer (bencode, XML, HTML)
│   └── hooks/           # User-defined lifecycle hooks (optional)
├── config/
│   ├── phoenix.default.php    # Default configuration (DO NOT EDIT)
│   └── phoenix.custom.php     # Your configuration (gitignored)
├── bin/                 # Cron maintenance scripts
│   ├── backup-database.php
│   └── clean-and-optimize.php
└── tests/               # PHPUnit test suite
```

### Architecture Notes

- **Entry points** (`public/*.php`) are thin: they bootstrap, then delegate to a controller.
- **Controllers** (`src/controller/*.php`) orchestrate each request: sanitize input → call model → call view. (`public/index.php` is small enough to skip the controller and call its model and view directly.)
- **Models** (`src/model/*.php`) handle all database operations. Each file exports one function that accepts `$connection`, `$settings`, and domain parameters.
- **Views** (`src/views/*.php`) handle presentation. Bencode for BitTorrent protocol, HTML for humans, XML/JSON for debugging. The bencode views build a plain PHP structure and serialise it through a single emitter, `bencode_encode()`, which guarantees correct length prefixes and BEP-3 dict key ordering.
- **Functions** (`src/functions/*.php`) contain business logic helpers that don't fit cleanly into model or view (sanitization, validation, address parsing, etc.).
- **Hooks** (`src/hooks/*.php`) are optional operator-defined scripts called at lifecycle points (peer.new, peer.stopped, download.complete, etc.). Keep them empty in this repo.

## Configuration

Configuration should take place in `config/phoenix.custom.php`, NOT `config/phoenix.default.php`. Phoenix _will_ attempt to use the default configuration if yours is missing.

### Stat-Tracking

Phoenix can log torrent events (completions by default; optionally started/stopped via `stats_events`) to an `events` table. Enable it with `$settings['stats_enabled'] = true;`, or from the **Statistics** section of the installer or the admin **Settings** flags — the table exists from install, so it's just a flag. The ledger is privacy-preserving by design — a coarse client label and minified location are derived from the peer_id and IP, which are never themselves stored. See the `stats_*` settings (including `stats_retention`, which prunes old rows) in `config/phoenix.default.php`.

#### Geo enrichment

With a GeoLite2 database, events are tagged with a coarse country/continent, and the admin **Geography** page maps active peers and completed downloads by country.

1. Run `composer require geoip2/geoip2`.
2. Get a free [GeoLite2-Country database](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) from MaxMind (their licence forbids Phoenix bundling it). Drop it where Phoenix finds it automatically — `/usr/share/GeoIP/GeoLite2-Country.mmdb` (kept current by MaxMind's `geoipupdate`), `/var/lib/GeoIP/`, or the project's `config/` directory — or set `$settings['stats_geo_database']` to a custom path.
3. Enable it with `$settings['stats_geo'] = true;`, or tick it in the installer / admin Settings — the toggle is greyed out there until both the library and a database are present.

The "active peers by country" map works as soon as geo is configured; "completed downloads by country" fills in from the events ledger as completions are logged, so it also needs `stats_enabled`. Geo degrades gracefully: with the library or database missing or unreadable, events are still logged (just with empty location codes) and the Geography page shows a "not configured" state.

### Two-Factor Authentication (optional)

The admin panel supports an optional TOTP second factor (the codes from authenticator apps like Google Authenticator or Aegis), on top of the admin password.

1. Run `composer require eustasy/authenticatron` (the QR code needs PHP's `gd` extension; without it the installer falls back to showing the secret and `otpauth://` URL for manual entry).
2. During install, scan the displayed QR code with your authenticator app, then enter a current code to confirm and enable 2FA. Leave the code blank to skip it — the panel stays password-only.

Once enabled, the login page asks for the 6-digit code alongside the password. To recover from a lost authenticator, remove the `$settings['admin_totp_secret'] = '...';` line from `config/phoenix.custom.php`; the panel reverts to password-only and you can re-enrol.

### Recovering admin access

The admin password is stored as a bcrypt hash (`$settings['admin_password']`) in `config/phoenix.custom.php`, and you normally change it from the panel's **Settings** page. If you lose it, edit that file directly. Generate a fresh hash:

```bash
php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Set `$settings['admin_password']` to the printed value. Alternatively, remove the `$settings['admin_password']` line (or set it to `''`): with no password set the panel skips authentication entirely, so you can open it and set a new password from **Settings** straight away — only do this when `public/admin.php` is not publicly reachable.

If you relocated `public/admin.php` out of the web root after setup, restore it first. Lost your two-factor device as well? Removing `$settings['admin_totp_secret']` reverts the panel to password-only (see [Two-Factor Authentication](#two-factor-authentication-optional)).

## Server Configuration

Phoenix ships with example web server configurations covering document root location, `.php` extension stripping, admin endpoint rate limiting, https redirection, and auth passthrough:

- [APACHE.md](./APACHE.md)
- [NGINX.md](./NGINX.md)

## Cron (Automating Maintenance)

1. Edit `config/phoenix.custom.php` and set:
    - `$settings['backup_dir']` to change the backup directory. Defaults to `backups/` in the project root.
    - `$settings['clean_with_cron']` to `true` to enable the script and disable occasional cleanup on announce.
2. Edit your crontab with `crontab -e`, and add entries like the following. Adjust the times and verify the paths are correct.

```cron
15 * * * * php ~/phoenix/bin/clean-and-optimize.php
30 * * * * php ~/phoenix/bin/backup-database.php
```

## Documentation

- [MIGRATING.md](./MIGRATING.md) — upgrading from 3.x to 4.0.
- [APACHE.md](./APACHE.md) — web server configuration for Apache 2.4.
- [NGINX.md](./NGINX.md) — web server configuration for Nginx.
- [CONTRIBUTING.md](./CONTRIBUTING.md) — development environment, tests, and contribution conventions.
- [ALTERNATIVES.md](./ALTERNATIVES.md) — other BitTorrent tracker projects.
- [CHANGELOG.md](./CHANGELOG.md) — release history.
- [LICENSE.md](./LICENSE.md) — MIT licence.
