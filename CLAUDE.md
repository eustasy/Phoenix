# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Phoenix is a lightweight BitTorrent tracker written in procedural PHP with a MySQL/MariaDB backend. It implements the announce/scrape protocols (BEP 3, BEP 7, BEP 15, BEP 23). It does not host a torrent listing site — though `index.php` provides a minimal public listing of explicitly-listed torrents and `magnet.php` is a client-side magnet generator.

## Common commands

```bash
# Run the full PHP test suite (requires a reachable DB and phoenix.custom.php)
php _tests/phoenix.php

# Run a single test by including only the bootstrap + that file
php -r '$settings = []; require "_tests/phoenix.php";' # runs everything; there is no per-file runner
# To run one test in isolation, comment out the glob loop in _tests/phoenix.php
# and require_once just the test you want.

# CI lint checks (what GitHub Actions actually runs — does NOT run the PHP test suite)
./.normal/check-php.sh
sql-lint .
php .normal/check-json.php
php .normal/check-xml.php
```

CI runs the workflow in `.github/workflows/normal.yml` against PHP 8.3–8.6. **It only runs lint checks; the PHP unit-test suite under `_tests/` is not invoked by CI** — those tests are for local/manual use and require a configured database.

## Architecture

### "Puff" structure

The codebase follows what the changelog calls a "puff-style" layout — small, single-purpose files glued together by `require_once`:

- **`_functions/phoenix/`** — one function per file. File `function.peer.new.php` defines `peer_new()`. Functions are pure-ish PHP (no top-level execution beyond defining the function). When extracting logic from a once, prefer this layout: one function per file, `////\t<function_name>` header comment, then a brief description, then the function.
- **`_onces/phoenix/`** — procedural code blocks pulled in via `require_once` exactly once per request. They share scope with their caller (read/write `$settings`, `$peer`, `$connection`, `$time`, etc. directly). Treat onces as procedural fragments, not as functions.
- **`_hooks/phoenix/`** — empty stubs (e.g. `phoenix.peer.new.php`) that operators can fill in. The tracker `include`s them at well-defined lifecycle points only when `is_readable()`. Keep them empty in this repo.
- **`_include/`** — HTML template fragments included by `admin.php` (`install-form.php`, `install-do.php`, `admin-panel.php`). Distinct from `_onces/` — these are presentation, not logic.
- **`_settings/`** — `phoenix.default.php` is the template (do not modify). User configuration goes into `phoenix.custom.php` (gitignored, created by the installer).
- **`_cron/hourly/`** — standalone scripts intended for cron (`backup-database.php`, `clean-and-optimize.php`). They `require_once '../../_phoenix.php'` to bootstrap.
- **`_tests/phoenix/`** — one test file per function/component (see test runner notes below).

### Entry points

Every entry point bootstraps via `require_once '_phoenix.php'` (which loads settings, opens the DB, defines `tracker_error`):

- `announce.php` — BEP 3 announce. Pulls in sanitization onces, then `once.announce.peer.event.php` (insert/update/delete/access depending on event), then `once.announce.torrent.php` (build response).
- `scrape.php` — BEP 15 scrape. `?stats` returns tracker stats; with `info_hash`(es) returns specific torrents; otherwise full scrape (if enabled).
- `index.php` — public torrent index, gated by `$settings['public_index']`.
- `admin.php` — admin panel and first-run installer. Requires no `phoenix.custom.php` to enter installer mode.
- `magnet.php` — pure client-side magnet generator. **Does not bootstrap** `_phoenix.php` and does not touch the tracker — it's a self-contained utility page.

### Bootstrap (`_phoenix.php`)

Sets path constants on `$settings` (`functions`, `hooks`, `onces`, `settings`), then loads `phoenix.default.php` followed by `phoenix.custom.php` (or hard-coded fallbacks if missing). It then `require_once`s `function.tracker.error.php` and `once.db.connect.php`. After this point, scripts can rely on `$connection`, `$settings`, `$time`, and `$chance` being in scope.

**Gotcha:** `once.db.connect.php` mutates `$settings['db_host']` in place — when `db_persist` is true it prepends `p:`. Anywhere outside `mysqli_connect` that reads `db_host` (e.g. `_cron/hourly/backup-database.php` writing a credentials file) must strip the `p:` prefix.

### Database

Three MyISAM tables (chosen for write-heavy workload, no transactions/foreign keys):

- `<prefix>peers` — active peers, ephemeral. PK `(info_hash, peer_id)`. Cleanup deletes rows where `updated < time - 3 * announce_interval`.
- `<prefix>torrents` — tracked torrents. PK `info_hash`. Holds `name`, `size`, `listed`, `downloads`.
- `<prefix>tasks` — task log (`name` PK).

`info_hash` and `peer_id` are stored as 40-char hex strings, not raw 20-byte binary. Conversion happens at the boundary via `maybe_binary_to_hex()` (in `function.sanitize.maybe_binary_to_hex.php`). This is the project's primary SQL injection defense: the hex sanitizer ensures these values can't carry SQL metacharacters into the many string-concatenated queries in the codebase.

### Settings model

Configuration is a single flat `$settings` array threaded through every function call. New tunables go in `phoenix.default.php` with a sensible default and a one-line `/* comment */`. The user-facing override file is `phoenix.custom.php` — code reads `$settings['foo']` directly with no fallback layer, so every key MUST exist in `phoenix.default.php`.

The installer (`admin.php` → `_include/install-do.php`) generates `phoenix.custom.php` by writing `$settings['key'] = 'value';` lines for the keys it knows about.

## Test runner

`_tests/phoenix.php` is the runner. It:

1. Loads `_phoenix.php` and overrides `$settings['db_prefix']` with a `TESTING_` suffix so tests can't touch production tables.
2. Initialises `$failure = false`.
3. Globs `_tests/phoenix/*.php` (alphabetical order) and `require_once`s each.
4. Exits 1 if `$failure` is set by any test.

All test files share a single PHP scope. `$failure`, `$connection`, `$settings`, `$time`, etc. are visible in every test. There is no per-test isolation — tests that mutate the DB must clean up after themselves (or document why they don't, like `test.task.clean.php`).

To test functions that call `exit()` (notably `tracker_error()`), use `ob_start()` + `register_shutdown_function()` to capture output and override the exit code — see `test.tracker.error.php`.

## Conventions

These come from `.github/CONTRIBUTING.md` and consistent practice in the codebase:

- **Tabs for indentation**, spaces for alignment.
- **"Four stroke" section headers**: `////\tName`, followed by a short `//` description. Used both at file top-level and inside functions to mark logical sections.
- **No closing `?>`** on PHP-only files.
- **One function per file** in `_functions/phoenix/`, named `function.<category>.<verb>.php`.
- **PHP-native solutions** over shell scripts when adding maintenance/utility code, so configuration stays in `$settings` rather than being spread across language boundaries (e.g. `_cron/hourly/backup-database.php`, not `.sh`).
- **Settings over hardcoded behavior**: any tunable parameter (size, count, on/off, path) gets a setting in `phoenix.default.php` with a sensible default.

## Commits

- Use `Fix #<issue>: <Title from issue>.` verbatim from the GitHub issue when the work closes a tracked issue.
- Otherwise use a short descriptive subject, present tense.
- One concern per commit — avoid batching unrelated fixes.
- Include `Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>` (or current model) trailer.

Run `gh issue view <N>` before writing a commit message to confirm the issue is still open and to copy its title verbatim.
