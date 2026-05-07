# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Phoenix is a lightweight BitTorrent tracker written in procedural PHP with a MySQL/MariaDB backend. It implements the announce/scrape protocols (BEP 3, BEP 7, BEP 15, BEP 23). It does not host a torrent listing site — though `public/index.php` provides a minimal public listing of explicitly-listed torrents and `public/magnet.php` is a client-side magnet generator.

## Common commands

```bash
# Install dev dependencies (PHPUnit). Required before running tests.
composer install

# Run the full PHPUnit suite (requires a reachable DB and phoenix.custom.php).
vendor/bin/phpunit

# Run a single test class.
vendor/bin/phpunit --filter ParseIpv4Test

# Run a single test method.
vendor/bin/phpunit --filter 'ParseIpv4Test::testIpv4WithPort'

# CI lint checks (run by GitHub Actions in addition to PHPUnit).
./.normal/check-php.sh
sql-lint .
php .normal/check-json.php
php .normal/check-xml.php
```

CI runs the workflow in `.github/workflows/normal.yml` against PHP 8.3–8.6. It runs both the lint checks and the PHPUnit suite (the latter against a MariaDB service container).

## Architecture

### "Puff" structure

The codebase follows what the changelog calls a "puff-style" layout — small, single-purpose files glued together by `require_once`:

- **`src/functions/`** — one function per file. File `function.peer.new.php` defines `peer_new()`. Functions are pure-ish PHP (no top-level execution beyond defining the function). Business logic helpers: sanitization, validation, address parsing, peer selection strategies, etc. One function per file, `////    <function_name>` header comment, then a brief description, then the function.
- **`src/model/`** — database operations. One function per file, each returns results or false. All queries live here.
- **`src/views/`** — presentation layer. Bencode, XML, and HTML output functions. Receives normalized data arrays, never raw DB results or `$_GET`/`$_POST`.
- **`src/hooks/`** — empty stubs (e.g. `phoenix.peer.new.php`) that operators can fill in. The tracker calls `phoenix_hook()` at well-defined lifecycle points; that helper checks `is_readable()` and `include`s the hook from inside its own scope. Hooks therefore see exactly `$connection`, `$settings`, `$time`, and `$peer` (the last passed by reference, so mutations propagate). Keep them empty in this repo.
- **`config/`** — `phoenix.default.php` is the template (do not modify). User configuration goes into `phoenix.custom.php` (gitignored, created by the installer).
- **`bin/`** — standalone scripts intended for cron (`backup-database.php`, `clean-and-optimize.php`). They `require_once '../src/phoenix.php'` to bootstrap.
- **`tests/phoenix/`** — one PHPUnit test class per function/component (see test runner notes below).

### Entry points

Every entry point sits in `public` and bootstraps via `require_once __DIR__.'/../src/phoenix.php'` (which loads settings, opens the DB, defines `tracker_error`):

- `announce.php` — BEP 3 announce. Follows MVC pattern: sanitizes input, resolves peer addresses, handles peer events (new/change/access/stopped/completed), then builds and outputs bencode response.
- `scrape.php` — BEP 15 scrape. `?stats` returns tracker stats; with `info_hash`(es) returns specific torrents; otherwise full scrape (if enabled).
- `index.php` — public torrent index, gated by `$settings['public_index']`.
- `admin.php` — admin panel and first-run installer. Requires no `phoenix.custom.php` to enter installer mode.
- `magnet.php` — pure client-side magnet generator. **Does not bootstrap** `../src/phoenix.php` and does not touch the tracker — it's a self-contained utility page.

### Web exposure

Only `public/` is meant to be web-served. The PDS layout puts `src/` (functions, model, views, hooks), `bin/` (cron scripts), `config/` (database credentials in `phoenix.custom.php`), and `tests/` one level above the document root, so when the server is configured correctly none of them are reachable over HTTP. Server-config docs live in `APACHE.md` and `NGINX.md`; both cover document root configuration, stripping `.php` from URLs, and rate-limiting the admin endpoint.

### Bootstrap (`src/phoenix.php`)

Orchestration only — the meaningful work lives in extracted, unit-testable functions:

- `settings_load()` (in `function.settings.load.php`) loads `phoenix.default.php` followed by `phoenix.custom.php` (or hard-coded fallbacks if the custom file is missing) and returns the populated `$settings` array.
- `db_connect()` (in `function.db.connect.php`) wraps `mysqli_connect()` in a try/catch so callers always get a `mysqli` or `false`, regardless of `mysqli_report()` mode (PHP 8.1+ defaults to throwing).

Bootstrap then `require_once`s `function.tracker.error.php`, runs `db_is_configured`, applies `db_persist_host`, calls `db_connect`, and (for closed trackers) loads the allowed-torrents list. After this point, scripts can rely on `$connection`, `$settings`, and `$time` being in scope.

**`tracker_error` is the one shared helper that lives in the bootstrap rather than each function.** Every entry point either bootstraps via `phoenix.php` or includes `function.tracker.error.php` explicitly (see `public/admin.php`, which loads it before its installer-mode branch can run without `phoenix.php`). Functions calling `tracker_error()` therefore do *not* `require_once` it themselves — the puff-style "declare your own deps" rule has this single carve-out. Any new entry point that skips `phoenix.php` must include `function.tracker.error.php` explicitly.

**Gotcha:** The DB connection logic mutates `$settings['db_host']` in place — when `db_persist` is true it prepends `p:`. Anywhere outside `mysqli_connect` that reads `db_host` (e.g. `bin/backup-database.php` writing a credentials file) must strip the `p:` prefix.

### Database

Three MyISAM tables (chosen for write-heavy workload, no transactions/foreign keys):

- `<prefix>peers` — active peers, ephemeral. PK `(info_hash, peer_id)`. Cleanup deletes rows where `updated < time - 3 * announce_interval`.
- `<prefix>torrents` — tracked torrents. PK `info_hash`. Holds `name`, `size`, `listed`, `downloads`.
- `<prefix>tasks` — task log (`name` PK).

Schema lives in `sql/<table>.sql`, one CREATE TABLE per file using the literal default prefix `phoenix_`. `db_create()` reads each file, rewrites the prefix to `$settings['db_prefix']` if different, and executes against the connection's selected database. Files are also importable manually with `mysql <database> < sql/peers.sql` for installs that bypass the wizard.

`info_hash` and `peer_id` are stored as 40-char hex strings, not raw 20-byte binary. Conversion happens at the boundary via `maybe_binary_to_hex()` (in `function.sanitize.maybe_binary_to_hex.php`). This is the project's primary SQL injection defense: the hex sanitizer ensures these values can't carry SQL metacharacters into the many string-concatenated queries in the codebase.

### Settings model

Configuration is a single flat `$settings` array threaded through every function call. New tunables go in `phoenix.default.php` with a sensible default and a one-line `/* comment */`. The user-facing override file is `phoenix.custom.php` — code reads `$settings['foo']` directly with no fallback layer, so every key MUST exist in `phoenix.default.php`.

The installer (`public/admin.php` in installer mode) generates `config/phoenix.custom.php` by writing `$settings['key'] = 'value';` lines for the keys it knows about.

## Test runner

PHPUnit is wired up via `composer.json` and `phpunit.xml.dist`. The test bootstrap (`tests/bootstrap.php`):

1. Loads Composer's autoloader and `src/phoenix.php` (giving access to `$connection`, `$settings`, `$time`).
2. Suffixes `$settings['db_prefix']` with `TESTING_` so tests can't touch production tables.
3. Calls `db_create()` to ensure the prefixed tables exist.
4. Exposes `$connection`, `$settings`, `$time` via `$GLOBALS` so each test class can pick them up in `setUpBeforeClass()`.

All test classes live in the `Phoenix\Tests` namespace and extend `PhoenixTestCase` (in `tests/phoenix/PhoenixTestCase.php`), which copies the globals into `protected static` properties (`self::$connection`, `self::$settings`, `self::$time`).

Test classes are autoloaded via PSR-4 (`Phoenix\Tests\` → `tests/phoenix/`), so files must be named in PascalCase matching the class (e.g. `ParseIpv4Test.php` → `class ParseIpv4Test`). PHPUnit discovers classes ending in `Test` automatically.

Tests that mutate the DB should clean up in `tearDown()` using the `__TEST_%` LIKE pattern; `task_clean()` already removes such rows so its test relies on that rather than fixture cleanup.

To test functions that call `exit()` (notably `tracker_error()`), spawn a subprocess via `proc_open(PHP_BINARY, ...)` and assert against captured stdout + exit code — see `TrackerErrorTest.php`. Running it in-process would terminate the PHPUnit worker.

## Conventions

These come from `.github/CONTRIBUTING.md` and consistent practice in the codebase:

- **Tabs for indentation**, spaces for alignment.
- **"Four stroke" section headers**: `////  Name`, followed by a short `//` description. Used both at file top-level and inside functions to mark logical sections.
- **No closing `?>`** on PHP-only files.
- **One function per file** in `src/functions/`, named `function.<category>.<verb>.php`.
- **PHP-native solutions** over shell scripts when adding maintenance/utility code, so configuration stays in `$settings` rather than being spread across language boundaries (e.g. `bin/backup-database.php`, not `.sh`).
- **Settings over hardcoded behavior**: any tunable parameter (size, count, on/off, path) gets a setting in `phoenix.default.php` with a sensible default.

## Commits

- Use `Fix #<issue>: <Title from issue>.` verbatim from the GitHub issue when the work closes a tracked issue.
- Otherwise use a short descriptive subject, present tense.
- One concern per commit — avoid batching unrelated fixes.
- Include `Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>` (or current model) trailer.

Run `gh issue view <N>` before writing a commit message to confirm the issue is still open and to copy its title verbatim.
