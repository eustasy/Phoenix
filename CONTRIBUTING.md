# Contributing to Phoenix

Phoenix follows what the changelog calls a "puff-style" layout: small,
single-purpose files glued together by `require_once`. These conventions keep
that layout navigable; CI enforces most of them (qlty runs phpstan and
php-cs-fixer, `ConventionsTest` checks the structural rules).

## Table of Contents

- [Local development environment](#local-development-environment)
  - [Docker (recommended)](#docker-recommended)
    - [Completing setup (the setup token)](#completing-setup-the-setup-token)
    - [Database credentials](#database-credentials)
    - [Reloading code without wiping the database](#reloading-code-without-wiping-the-database)
    - [Keeping your config across reloads](#keeping-your-config-across-reloads)
  - [Without Docker](#without-docker)
- [Project Structure](#project-structure)
  - [Architecture Notes](#architecture-notes)
- [Conventions](#conventions)
  - [One function per file](#one-function-per-file)
  - [File layout](#file-layout)
  - [Style](#style)
- [Tests](#tests)
- [Commits](#commits)

## Local development environment

### Docker (recommended)

The repo ships a disposable Docker environment: MariaDB plus PHP (with `mysqli`
and `gd`), Composer, and a MariaDB-compatible `mysqldump` — mirroring the
smoke-test CI. It starts with no configuration, so it lands in the installer.

```bash
# From the repo root:
docker compose -f docker-compose.dev.yml up --build
```

Then open <http://localhost:8000/admin.php> and run **Setup**. The installer is
gated behind a one-time setup token (see
[Completing setup](#completing-setup-the-setup-token) below); for the database,
enter host `db`, name `phoenix`, user `phoenix`, password `phoenix_pass` (see
[Database credentials](#database-credentials)). After install you can log in and
exercise every admin page (dashboard, torrents, backups, settings), enrol 2FA
from the installer's QR, and run an on-demand backup.

Leave **Persistent connections** unchecked here. The container serves with PHP's
built-in `php -S` — a single long-lived process — and a persistent (`p:`)
mysqli connection reused across requests in it can segfault the server
(`free(): invalid pointer`) under a burst of requests, such as a bulk upload.
The pooled workers of a production Apache/PHP-FPM setup don't have this problem.

```bash
# Stop and wipe the database (next start is a fresh installer again):
docker compose -f docker-compose.dev.yml down -v
```

The working tree is mounted read-only and copied into the container by
`docker/entrypoint.sh`, so nothing the installer writes — config, tables,
`vendor/` — touches your checkout; the environment is throwaway. The first
build compiles the PHP extensions; later starts reuse the cached image.

#### Completing setup (the setup token)

The installer is gated behind a one-time **setup token**, so a stray open port
can't let someone else run Setup before you do. Phoenix writes it to a
server-only file the first time `/admin.php` renders the installer. Because the
working tree is mounted read-only and copied into the container, that file lands
**inside the `web` container** at `/app/config/.phoenix-setup-token`, not in your
host checkout — so read it out of the container and paste it into the installer's
token field:

```bash
# Load http://localhost:8000/admin.php once first (the token is created lazily),
# then read it from the running web container:
docker compose -f docker-compose.dev.yml exec web cat config/.phoenix-setup-token
```

The token is deleted once Setup succeeds; a fresh `up` after `down -v` mints a
new one.

#### Database credentials

`docker-compose.dev.yml` hardcodes these throwaway dev-only credentials (also
echoed in the container's startup banner):

| Field         | Value          |
| ------------- | -------------- |
| Host          | `db`           |
| Database      | `phoenix`      |
| User          | `phoenix`      |
| Password      | `phoenix_pass` |
| Root password | `phoenix_root` |

The host is the Compose service name `db`, not `localhost` — the containers talk
over the Compose network by service name. To open a shell against the database
directly:

```bash
# As the phoenix user:
docker compose -f docker-compose.dev.yml exec db mariadb -uphoenix -pphoenix_pass phoenix

# As root (e.g. to GRANT or inspect):
docker compose -f docker-compose.dev.yml exec db mariadb -uroot -pphoenix_root
```

#### Reloading code without wiping the database

The web container copies the tree in at startup, so host changes are picked up
by recreating it — not by `down -v`, which also drops the database volume. To
reload code while keeping the data:

```bash
# Re-run the entrypoint (re-copies the tree, re-installs deps); DB volume kept.
docker compose -f docker-compose.dev.yml restart web

# Or rebuild the image too, for Dockerfile / PHP-extension changes:
docker compose -f docker-compose.dev.yml up -d --build web
```

#### Keeping your config across reloads

The entrypoint deletes `config/phoenix.custom.php` on every start, so a reload
drops you back into the installer — though the database itself is untouched, so
you can just re-run Setup against the existing tables. To skip that, copy the
config out once and back in after a reload (the server reads it per request, so
no extra restart is needed):

```bash
# Save the generated config (after the first install):
docker compose -f docker-compose.dev.yml cp web:/app/config/phoenix.custom.php ./phoenix.custom.php

# Restore it after a reload (the entrypoint cleared it at startup):
docker compose -f docker-compose.dev.yml cp ./phoenix.custom.php web:/app/config/phoenix.custom.php
```

`*.custom.*` is gitignored, so the saved copy is never committed.

### Without Docker

Install Composer dependencies, then run the test suite against a reachable
MariaDB instance (see the test runner notes in CLAUDE.md for bootstrap details):

```bash
composer install
vendor/bin/phpunit
```

See the **Tests** section below for conventions.

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

## Conventions

Structural rules for the `src/` layers, enforced by `ConventionsTest` and the
linters.

### One function per file

**Every file in `src/functions/`, `src/model/`, `src/views/`, and
`src/controller/` defines exactly one function.** Helpers get their own file,
not a second function below the main one — `tests/phoenix/ConventionsTest.php`
fails the suite otherwise.

- The file name mirrors the function name, with underscores becoming dots:
  `parse_ipv4()` lives in `parse.ipv4.php`, `stats_client_version()` in
  `stats.client.version.php`.
- A function `require_once`s the helpers it calls at the top of its own body
  (see `torrent_parse()`), so every file declares its own dependencies.
- The one carve-out: `tracker_error()` is loaded by the bootstrap, so
  functions never `require_once` it themselves.
- `src/hooks/` files are scripts, not function definitions — they execute
  inside `phoenix_hook()`'s scope and must **not** declare functions at top
  level (the dispatcher uses plain `include`, so a declaration would fatal on
  the second event in a process).

### File layout

- `declare(strict_types=1);` first in every PHP file.
- A "four stroke" section header naming the function — `////` then a tab,
  then the function name — followed by a short `//` description, then the
  function. The same header style marks logical sections inside functions.
- No closing `?>` on PHP-only files.

### Style

- PHP is PSR-12 — four-space indentation — enforced by php-cs-fixer via
  `qlty check` (config in `.qlty/configs/.php-cs-fixer.dist.php`). SQL files
  use tabs.
- phpstan runs at level 9; project-wide array shapes (`PhoenixSettings`,
  `PhoenixPeer`) are type aliases in `.qlty/configs/phpstan.dist.neon`.
- Settings over hardcoded behavior: any tunable gets a key in
  `config/phoenix.default.php` with a sensible default and a one-line
  comment. Code reads `$settings['key']` directly, so every key must exist
  in the default file.
- PHP-native solutions over shell scripts for maintenance/utility code, so
  configuration stays in `$settings` (e.g. `bin/backup-database.php`, not a
  `.sh`).

## Tests

- One PHPUnit test class per function/component in `tests/phoenix/`, PSR-4
  named (`ParseIpv4Test.php` → `class ParseIpv4Test`), extending
  `PhoenixTestCase`.
- Tests that mutate the database use `__TEST_%`-prefixed fixture values and
  clean up in `tearDown()`.
- Functions that `exit()` (like `tracker_error()`) are tested in a
  subprocess — see `TrackerErrorTest`.

## Commits

- `Fix #<issue>: <Title from issue>.` verbatim when the work closes a
  tracked issue; otherwise a short descriptive subject in present tense.
- One concern per commit.
