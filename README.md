# Phoenix v4.0beta3

[![Normal (PHP)](https://github.com/eustasy/Phoenix/actions/workflows/php.yml/badge.svg)](https://github.com/eustasy/Phoenix/actions/workflows/php.yml)
[![Test (PHP)](https://github.com/eustasy/Phoenix/actions/workflows/test-php.yml/badge.svg)](https://github.com/eustasy/Phoenix/actions/workflows/test-php.yml)
[![Smoke (PHP)](https://github.com/eustasy/Phoenix/actions/workflows/smoke-php.yml/badge.svg)](https://github.com/eustasy/Phoenix/actions/workflows/smoke-php.yml)
[![Normal (SQL)](https://github.com/eustasy/Phoenix/actions/workflows/sql.yml/badge.svg)](https://github.com/eustasy/Phoenix/actions/workflows/sql.yml)
[![Maintainability](https://qlty.sh/gh/eustasy/projects/Phoenix/maintainability.svg)](https://qlty.sh/gh/eustasy/projects/Phoenix)
[![Code Coverage](https://qlty.sh/gh/eustasy/projects/Phoenix/coverage.svg)](https://qlty.sh/gh/eustasy/projects/Phoenix)

A lightweight BitTorrent Tracker written in PHP, with an SQL backend, for people that just want to host a tracker, not the torrent listing site.

## Installation

> **Upgrading from 3.x?** The document root, configuration, and cron paths have all moved in 4.0. Follow the [3.x → 4.0 Migration Guide](./MIGRATING.md) before deploying. See [CHANGELOG.md](./CHANGELOG.md) for the full list of changes.

### What Do You Need?

#### Required

* PHP >= 8.2 with the `mysqli` and `xml` extensions. The bundled `date`, `filter`, `json`, `pcre`, and `session` extensions are also used (these ship enabled by default).
* A PHP-compatible web server (Apache or Nginx).
* A MySQL-compatible database.

#### Recommended

* [The latest supported version of PHP](https://www.php.net/supported-versions.php)
* The latest version of Nginx (>= 1.18 with HTTP/2) or Apache 2.4.
* The latest version of MariaDB.

### Install Guide

1. Copy `config/phoenix.default.php` to `config/phoenix.custom.php`
2. Edit the variables in `config/phoenix.custom.php`
3. Upload Phoenix to your server.
4. Point your web server's document root at the `public/` directory. Only `public/` should be web-reachable; `src/`, `bin/`, `config/`, and `tests/` must remain outside the document root so configuration (including database credentials) is never served. See [APACHE.md](./APACHE.md) or [NGINX.md](./NGINX.md) for example configurations.
5. Load `admin.php` in your browser and run the `Setup` option.
6. After setup, move `public/admin.php` into `src/` (`mv public/admin.php src/admin.php`) so it stops being web-reachable. Move it back temporarily if you ever need to re-run setup.

## Project Structure

Phoenix follows an MVC-inspired structure optimized for procedural PHP:

```text
phoenix/
├── public/              # Web-accessible entry points (thin; delegate to src/controller/)
│   ├── announce.php     # BitTorrent announce endpoint (BEP 3)
│   ├── scrape.php       # BitTorrent scrape endpoint (BEP 15)
│   ├── index.php        # Public torrent listing (optional)
│   ├── api.php          # Management API, routed by action (API-key authenticated)
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

* **Entry points** (`public/*.php`) are thin: they bootstrap, then delegate to a controller.
* **Controllers** (`src/controller/*.php`) orchestrate each request: sanitize input → call model → call view. (`public/index.php` is small enough to skip the controller and call its model and view directly.)
* **Models** (`src/model/*.php`) handle all database operations. Each file exports one function that accepts `$connection`, `$settings`, and domain parameters.
* **Views** (`src/views/*.php`) handle presentation. Bencode for BitTorrent protocol, HTML for humans, XML/JSON for debugging. The bencode views build a plain PHP structure and serialise it through a single emitter, `bencode_encode()`, which guarantees correct length prefixes and BEP-3 dict key ordering.
* **Functions** (`src/functions/*.php`) contain business logic helpers that don't fit cleanly into model or view (sanitization, validation, address parsing, etc.).
* **Hooks** (`src/hooks/*.php`) are optional operator-defined scripts called at lifecycle points (peer.new, peer.stopped, download.complete, etc.). Keep them empty in this repo.

## Configuration

Configuration should take place in `config/phoenix.custom.php`, NOT `config/phoenix.default.php`. Phoenix _will_ attempt to use the default configuration if yours is missing.

### Stat-Tracking

Phoenix can log torrent events (completions by default; optionally started/stopped via `stats_events`) to an `events` table — see the `stats_*` settings in `config/phoenix.default.php`. The table exists from install, so enabling it is a pure config flip: `$settings['stats_enabled'] = true;`. The ledger is privacy-preserving by design — a coarse client label and minified location are derived from the peer_id and IP, which are never themselves stored.

To enrich events with the minified geo location (country + continent ISO codes):

1. Run `composer require geoip2/geoip2`.
2. Download a free [GeoLite2-Country database](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) from MaxMind (their license forbids Phoenix bundling it).
3. Set `$settings['stats_geo'] = true;` and point `$settings['stats_geo_database']` at the `.mmdb` file.

Geo enrichment degrades gracefully: when the library or database file is missing or unreadable, events are still logged, just with empty location codes.

## Server Configuration

Phoenix ships with example web server configurations covering document root, `.php` extension stripping, and admin endpoint rate limiting:

* [APACHE.md](./APACHE.md)
* [NGINX.md](./NGINX.md)

### Cron (Automating Maintenance)

1. Edit `config/phoenix.custom.php` and set:
     * `$settings['backup_dir']` to change the backup directory. Defaults to `backups`.
     * `$settings['clean_with_cron']` to `true` to enable the script and disable occaisional cleanup on announce.
2. Edit your crontab with `crontab -e`, and add a crontab like the following. You can edit the times, and should make sure the paths are correct by running the commands after the asterisks.

```cron
15 * * * * php ~/phoenix/bin/clean-and-optimize.php
30 * * * * php ~/phoenix/bin/backup-database.php
```
