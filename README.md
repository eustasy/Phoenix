# Phoenix v4.3beta8

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
  - [Stat-Tracking](#stat-tracking)
    - [Geo enrichment](#geo-enrichment)
  - [Two-Factor Authentication (optional)](#two-factor-authentication-optional)
  - [Admin password requirements](#admin-password-requirements)
  - [Recovering admin access](#recovering-admin-access)
  - [Reverse proxies & client IP address](#reverse-proxies--client-ip-address)
- [Server Configuration](#server-configuration)
- [Cron (Automating Maintenance)](#cron-automating-maintenance)
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

### Admin password requirements

The admin password is set in three places — the installer, the **Settings** page's _Change password_ action, and the first-run set-password gate — all sharing one policy (following NIST SP 800-63B: length over composition):

- **At least 12 characters.**
- **At most 72 bytes** — bcrypt (`PASSWORD_DEFAULT`) silently truncates beyond 72 bytes, so a longer passphrase would have its tail ignored.

An optional TOTP second factor can be enrolled alongside the password (see [Two-Factor Authentication](#two-factor-authentication-optional)).

### Recovering admin access

The admin password is stored as a bcrypt hash (`$settings['admin_password']`) in `config/phoenix.custom.php`, and you normally change it from the panel's **Settings** page. If you're locked out, choose whichever path fits:

- **Reset from the panel.** Remove the `$settings['admin_password']` line from `config/phoenix.custom.php` (or set it to `''`). On the next load, `admin.php` presents a one-time **"set admin password"** gate — the same ≥12-character policy, with optional TOTP re-enrolment — and writes the new hash for you, so no manual hashing is needed. This needs `config/` to be writable, and you should only do it while `public/admin.php` is not publicly reachable, because the gate itself is unauthenticated. (Setting `$settings['admin_auth_optional'] = true` instead runs the panel with **no** password at all — only for an `admin.php` already protected by other means, such as reverse-proxy auth or an IP allowlist.)
- **Set a hash directly.** When `config/` isn't writable — or you'd rather not expose the gate — generate a hash and paste it in yourself:

  ```bash
  php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT), PHP_EOL;"
  ```

  Set `$settings['admin_password']` to the printed value. Editing the file directly bypasses the length policy, so pick a strong password yourself.

If you relocated `public/admin.php` out of the web root after setup, restore it first. To start over completely, delete `config/phoenix.custom.php` and re-run **Setup**; the installer writes a fresh [setup token](#installation) to `config/.phoenix-setup-token` that you must supply again. Lost your two-factor device as well? Removing `$settings['admin_totp_secret']` reverts the panel to password-only (see [Two-Factor Authentication](#two-factor-authentication-optional)).

### Reverse proxies & client IP address

Phoenix identifies each peer by its connecting IP, so behind a reverse proxy or CDN it must know which forwarded-address header to trust — otherwise it either sees only the proxy or lets clients spoof their address. By default it trusts **nothing** and uses the direct connection (`REMOTE_ADDR`) only: safe, but wrong behind a proxy. Two settings (both empty and fail-closed by default) control it:

- `$settings['forwarded_headers']` — an ordered list of headers to trust, e.g. `['x-forwarded-for']` or `['cf-connecting-ip']`. Recognised: `x-forwarded-for`, `forwarded` (RFC 7239), `x-real-ip`, `cf-connecting-ip`, `true-client-ip`, and the legacy `client-ip`. List **only** headers your proxy sets and strips from client input.
- `$settings['trusted_proxies']` — CIDR ranges of your proxies. A forwarded header is honoured only when `REMOTE_ADDR` falls inside one of these ranges; chain headers (`X-Forwarded-For` / `Forwarded`) are walked from the right, skipping these ranges, to find the real client.

If `trusted_proxies` is empty, forwarded headers are **not** trusted unless you explicitly set `$settings['trust_any_forwarded'] = true` — which trusts the header from any direct connection and so lets anyone reaching the tracker spoof their address. Leave it off unless you fully control who can connect. Often it is cleaner to let the web server rewrite `REMOTE_ADDR` itself (Apache `mod_remoteip`, Nginx `real_ip`) and leave these empty — see [APACHE.md](./APACHE.md) / [NGINX.md](./NGINX.md).

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
- [CONTRIBUTING.md](./CONTRIBUTING.md) — development environment, project structure, and contribution conventions.
- [ALTERNATIVES.md](./ALTERNATIVES.md) — other BitTorrent tracker projects.
- [CHANGELOG.md](./CHANGELOG.md) — release history.
- [LICENSE.md](./LICENSE.md) — MIT licence.
