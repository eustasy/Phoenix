# Dependencies

Everything Phoenix relies on outside its own code. Dependencies come from three
places: the server it runs on, Composer packages, and what the browser loads.
Each section starts with where those dependencies are declared. Server and
Composer dependencies are split into **Required**, **Recommended**,
**Optional**, and **Development**. Client dependencies are all loaded by
default, so they are a single list.

Composer is not needed to run Phoenix. Every Composer package is optional,
guarded with `class_exists()`, and the feature it enables stays switched off
without it. The admin **Diagnostics** page reports which are installed.

## Server

Declared in `composer.json` (`require`: the PHP version and extensions) and
[README.md](README.md#requirements). Web server configuration is in
[APACHE.md](APACHE.md) and [NGINX.md](NGINX.md), cron entries in
[CONFIGURATION.md](CONFIGURATION.md#cron-automating-maintenance), and the dev
image in `docker/Dockerfile`.

### Required (Server)

- **PHP 8.2 or later**, with `ext-mysqli`. The bundled `filter`, `json`,
  `session`, `xml`, `pcre` and `date` extensions are also used. They are on by
  default.
- **A MySQL-compatible database.** MariaDB is recommended. Measured ceilings in
  [LIMITS.md](LIMITS.md) were taken on MariaDB 11.8.
- **Apache 2.4 or Nginx 1.18+**, with the document root at `public/`.

### Recommended (Server)

- **`ext-gd`**, to draw the QR code when enrolling admin two-factor auth.
  Without it the installer shows the secret to type in by hand.
- **`ext-mbstring`**, which `eustasy/authenticatron` needs (see
  [Composer](#composer)).
- **`mysqldump` and `proc_open`**, for backups (`bin/backup-database.php` and
  the admin Backups page). **`ext-zlib`** compresses them when
  `backup_compress` is on.
- **cron**, to run `bin/` maintenance on a schedule. Without it, pruning runs on
  a share of announces, and backups only run when started from the admin panel.

### Optional (Server)

- **A GeoLite2-Country database** (`.mmdb`) from
  [MaxMind](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data), for
  geo stats. Its licence forbids bundling it. Phoenix looks for it at
  `stats_geo_database`, then `/usr/share/GeoIP/`, `/var/lib/GeoIP/`, and
  `config/`.
- **`ext-maxminddb`**, if geo stats are on. The pure-PHP reader works but is
  around 40× slower on the announce path.
- **`ext-excimer`**, for Sentry profiling. Inert without it.

### Development (Server)

- **Docker with Compose**, for the disposable dev environment in
  `docker-compose.dev.yml`: MariaDB, PHP with `mysqli` and `gd`, Composer, and
  `mysqldump`. See [CONTRIBUTING.md](CONTRIBUTING.md).
- **A reachable MySQL or MariaDB database**, for the test suite.
- **[qlty](https://qlty.sh)**, which runs phpstan, php-cs-fixer and the other
  linters CI enforces. Configuration is in `.qlty/`.

## Composer

Declared in `composer.json`: `suggest` for the optional packages, and
`require-dev` for development. Installed into `vendor/`.

### Required (Composer)

None.

### Recommended (Composer)

- **`eustasy/authenticatron` ^4.2**, for TOTP two-factor auth on the admin
  panel. Needs `ext-mbstring`, and `ext-gd` for the enrolment QR code. See
  [CONFIGURATION.md](CONFIGURATION.md) and [RECOVERY.md](RECOVERY.md).

### Optional (Composer)

- **`maxmind-db/reader` ^1.11**, for geo stats: country and continent on stat
  events, and the admin Geography page. Needs a GeoLite2-Country database, and
  `ext-maxminddb` for speed.
- **`sentry/sentry` ^4.31**, for error reporting to Sentry or a compatible
  monitor, through `report_errors` and the `src/hooks/` hooks.

### Development (Composer)

- **`phpunit/phpunit` ^11.0 or ^12.0.**
- **The three packages above**, also listed under `require-dev`, so the tests
  cover the features they enable.

## Client

Declared in `src/functions/cdn.assets.php` (libraries, pinned to exact versions
with Subresource Integrity hashes), `src/views/html.head.php` (web font), and
`public/assets/pwned-check.js` (password check). Every origin is allowed by the
Content Security Policy in `src/functions/http.security.headers.php`, so adding
one means widening that policy too.

Phoenix has no front-end build step. These load at runtime from third-party
origins, so the reader's browser contacts them, not the server.

- **[Lucide](https://lucide.dev) 1.44.0**, icons on every HTML page. From
  cdn.jsdelivr.net.
- **[Chart.js](https://www.chartjs.org) 4.5.1**, charts on the dashboard,
  Clients and Bandwidth pages. From cdn.jsdelivr.net.
- **[jsvectormap](https://github.com/themustafaomar/jsvectormap) 1.7.0**, with
  its world map data and stylesheet, for the Geography map. From
  cdn.jsdelivr.net.
- **[Inter](https://rsms.me/inter/)**, the web font on every HTML page, from
  Google Fonts (fonts.googleapis.com, fonts.gstatic.com). If blocked, pages fall
  back to the system sans-serif.
- **[Pwned Passwords](https://haveibeenpwned.com/API/v3#PwnedPasswords)**
  (api.pwnedpasswords.com), which warns when a password typed at install, setup
  or in Settings appears in a known breach. Only the first 5 characters of the
  password's SHA-1 hash leave the browser. Advisory only: it does nothing if
  blocked, and the server still enforces the password policy.

No Node or npm tooling is needed for development.
(`.github/workflows/test-js.yml` is a disabled template.)
