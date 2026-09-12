# Phoenix v4.3beta10

[![Normal (PHP)](https://github.com/eustasy/Phoenix/actions/workflows/php.yml/badge.svg)](https://github.com/eustasy/Phoenix/actions/workflows/php.yml)
[![Test (PHP)](https://github.com/eustasy/Phoenix/actions/workflows/test-php.yml/badge.svg)](https://github.com/eustasy/Phoenix/actions/workflows/test-php.yml)
[![Smoke (PHP)](https://github.com/eustasy/Phoenix/actions/workflows/smoke-php.yml/badge.svg)](https://github.com/eustasy/Phoenix/actions/workflows/smoke-php.yml)
[![Normal (SQL)](https://github.com/eustasy/Phoenix/actions/workflows/sql.yml/badge.svg)](https://github.com/eustasy/Phoenix/actions/workflows/sql.yml)
[![Maintainability](https://qlty.sh/gh/eustasy/projects/Phoenix/maintainability.svg)](https://qlty.sh/gh/eustasy/projects/Phoenix)
[![Code Coverage](https://qlty.sh/gh/eustasy/projects/Phoenix/coverage.svg)](https://qlty.sh/gh/eustasy/projects/Phoenix)

A lightweight BitTorrent Tracker written in PHP, with an SQL backend, for people that just want to host a tracker — with an optional public index, stats, and admin panel, but not a full torrent listing site.

## Table of Contents

- [Installation](#installation)
  - [Requirements](#requirements)
  - [With server access](#with-server-access)
  - [Managed LAMP / shared hosting](#managed-lamp--shared-hosting)
- [Configuration](#configuration)
- [Web Server Configuration](#web-server-configuration)
- [API](#api)
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
3. Load `public/admin.php` in your browser and run **Setup**. To prove you control the server, setup asks for a one-time token that Phoenix writes to `config/.phoenix-setup-token` on first load — open that file (over SSH or your host's file manager) and paste in its contents. Setup then creates the database tables and writes `config/phoenix.custom.php`; the token is removed once setup completes.
4. After setup, secure `admin.php` — the simplest approach is to remove it from `public/` (`mv public/admin.php src/admin.php`). Move it back temporarily if you ever need to re-run setup. Alternatively, rate-limit the endpoint; see [APACHE.md](./APACHE.md) or [NGINX.md](./NGINX.md).

### Managed LAMP / shared hosting

Use this path when you use a cPanel-style host with no direct web server configuration access.

1. Upload Phoenix to your server.
2. Set the site's document root to `public/` via your hosting control panel where possible. If the panel does not allow changing the document root, put the contents of `public/` in the web root and keep `src/`, `config/`, `bin/`, and `tests/` outside (above) it.
3. Apache hosts: add the `.php`-stripping rewrite rules from [APACHE.md](./APACHE.md) to `public/.htaccess` so clients can reach `/announce` without the `.php` suffix.
4. Create the database in your hosting control panel.
5. Set up the tracker — choose one:
    - Load `public/admin.php` in your browser and run **Setup**. It asks for the one-time token Phoenix writes to `config/.phoenix-setup-token` (open it in your host's file manager and paste it in), then creates the tables and writes `config/phoenix.custom.php` with your database credentials.
    - Or import the schema files manually (`sql/peers.sql`, `sql/torrents.sql`, `sql/tasks.sql`, `sql/events.sql`), copy `config/phoenix.default.php` to `config/phoenix.custom.php`, and fill in your database credentials.
6. After setup, secure `admin.php` as described above.

## Configuration

Configuration should take place in `config/phoenix.custom.php`, NOT `config/phoenix.default.php`. Phoenix _will_ attempt to use the default configuration if yours is missing.

**[CONFIGURATION.md](./CONFIGURATION.md)** covers every option in detail: stat-tracking and geo enrichment, two-factor authentication, admin password policy, reverse proxies and client IP address, error reporting, and the backup and maintenance cron jobs. Every settable value is listed with its default in `config/phoenix.default.php`.

## Web Server Configuration

Phoenix ships with example web server configurations covering document root location, `.php` extension stripping, admin endpoint rate limiting, https redirection, and auth passthrough:

- [APACHE.md](./APACHE.md)
- [NGINX.md](./NGINX.md)

## API

Every HTTP endpoint Phoenix exposes is documented in [API.md](./API.md) — the tracker protocol (`announce`, `scrape`), the public read endpoints (torrent index, tracker stats), and the authenticated management API for adding and editing torrents. Each entry lists its parameters, response shape in JSON and XML, and the errors it can return.

## Documentation

- [CONFIGURATION.md](./CONFIGURATION.md) — every configuration option, with defaults.
- [RECOVERY.md](./RECOVERY.md) — admin lockout, disabling 2FA, and restoring the database from a backup.
- [API.md](./API.md) — every HTTP endpoint: announce, scrape, the public index, and the management API.
- [MIGRATING.md](./MIGRATING.md) — upgrading from 3.x to 4.0.
- [LIMITS.md](./LIMITS.md) — how many peers and torrents an install carries, and what binds first.
- [APACHE.md](./APACHE.md) — web server configuration for Apache 2.4.
- [NGINX.md](./NGINX.md) — web server configuration for Nginx.
- [CONTRIBUTING.md](./CONTRIBUTING.md) — development environment, project structure, and contribution conventions.
- [ALTERNATIVES.md](./ALTERNATIVES.md) — other BitTorrent tracker projects.
- [CHANGELOG.md](./CHANGELOG.md) — release history.
- [LICENSE.md](./LICENSE.md) — MIT licence.
