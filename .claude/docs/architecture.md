# Architecture

## "Puff" structure

Small, single-purpose files glued together by `require_once` — the changelog
calls it "puff-style." A function declares its own dependencies: it
`require_once`s the helpers it calls at the top of its own body, never relying on
a caller to have loaded them. The single carve-out is `tracker_error()`, loaded
by the bootstrap (see below).

## The five layers under `src/`

- **`src/controller/`** — request handlers, one per endpoint/action. Each
  exposes a `*_controller()` function the matching entry point calls after
  bootstrap. Per-request input sanitization, model calls, and view selection
  live here. Controllers return a response string; the entry point echoes it.
- **`src/functions/`** — business-logic helpers (sanitization, validation,
  address parsing, peer selection, auth, bencode codec, etc.). Pure-ish PHP, no
  top-level execution beyond defining the function.
- **`src/model/`** — database operations. One function per file; each takes
  `$connection`, `$settings`, and domain params, and returns results or `false`.
  **All SQL lives here.**
- **`src/views/`** — presentation. Receives normalized data arrays, never raw DB
  results or `$_GET`/`$_POST`. Emits bencode / JSON / XML / HTML.
  See [views.md](views.md).
- **`src/hooks/`** — operator-customizable lifecycle scripts. See
  [stats-hooks.md](stats-hooks.md).

`src/partials/` holds static HTML fragments included by some admin views.

## Entry points (`public/`)

Only `public/` is meant to be web-served. Each entry point is thin: it
bootstraps via `require_once __DIR__.'/../src/phoenix.php'`, then delegates to a
controller. `announce.php` is ~12 lines.

- **`announce.php`** → `announce_controller()` — BEP 3 announce. Sanitize →
  validate info_hash/peer_id → resolve IPs/ports → rate-limit → dispatch on
  `?event=` (new/changed/access/stopped/completed, firing hooks) → probabilistic
  cleanup → build and render the peer list. Returns `''` for `?event=stopped`.
- **`scrape.php`** — routes by mode: `?stats` → `scrape_stats_controller`;
  `info_hash`(es) present → `scrape_specific_controller` (closed trackers filter
  to allowed hashes first, erroring if none remain rather than falling through);
  otherwise → `scrape_full_controller` (if `full_scrape` enabled).
- **`index.php`** — public torrent index, gated by `$settings['public_index']`.
  The exception to the thin-entry rule: small enough to call its model and view
  directly, no controller.
- **`admin.php`** — admin panel + first-run installer. Loads `tracker.error.php`
  explicitly (its installer branch runs before any DB connect). No
  `phoenix.custom.php` → installer mode (`admin_install_controller`). Otherwise:
  full bootstrap → `admin_login_controller` (auth gate) → `admin_panel_controller`
  (page router). See [http-api.md](http-api.md).
- **`magnet.php`** — self-contained client-side magnet generator. **Does not
  bootstrap** `phoenix.php` and never touches the tracker.
- **`api/**`** — the REST management API. Each endpoint is its own entry point
  (no central router); they pre-set `$_GET['json']` before bootstrap so errors
  serialise as JSON, then delegate to an `api_*_controller`.
  See [http-api.md](http-api.md).

## Web exposure (PDS layout)

`src/`, `bin/`, `config/`, and `tests/` sit one level above the document root,
so when the server points at `public/` they're unreachable over HTTP — keeping
DB credentials in `config/phoenix.custom.php` off the web. `APACHE.md` /
`NGINX.md` cover document root, `.php` stripping, and admin rate-limiting.

## Bootstrap (`src/phoenix.php`)

Orchestration only; the real work is in extracted, unit-testable functions. In
order, it:

1. Sets error handling baseline (`display_errors` off, `log_errors` on) — a
   printed warning would corrupt a binary bencode body. Sets
   `default_charset` to `iso-8859-1` for the binary protocol.
2. Conditionally loads the Composer autoloader (optional libs).
3. `settings_load()` (`settings.load.php`) loads `phoenix.default.php` then
   `phoenix.custom.php` (or hard-coded fallbacks) and returns `$settings`. Then
   injects `$settings['phoenix_version']`.
4. Resolves the GeoLite2 DB path only when `stats_geo` is on (keeps the announce
   hot path free of file stats).
5. `error_configure()` applies the operator's debug/error_log overrides.
6. `require_once`s `tracker.error.php`.
7. `db_is_configured` → `db_persist_host` → `db_connect()` (wraps
   `mysqli_connect()` in try/catch so callers always get a `mysqli` or `false`).
8. For closed trackers, loads `$allowed_torrents` (BEP 27 tracker-side filter).

After this, scripts can rely on `$connection`, `$settings`, and `$time`.

**`tracker_error` is the one shared helper that lives in bootstrap, not in each
function.** Every entry point either bootstraps via `phoenix.php` or includes
`tracker.error.php` explicitly (see `admin.php`). Functions calling
`tracker_error()` do **not** `require_once` it. Any new entry point that skips
`phoenix.php` must include `tracker.error.php` itself.

**Gotcha:** DB connection logic mutates `$settings['db_host']` in place —
prepends `p:` when `db_persist` is true. Anything outside `mysqli_connect` that
reads `db_host` (e.g. `bin/backup-database.php` writing a credentials file) must
strip the `p:` prefix. See [database.md](database.md).

## `bin/` cron scripts

`backup-database.php` and `clean-and-optimize.php` `require_once`
`../src/phoenix.php` to bootstrap, then call models/functions. PHP-native, not
shell, so config stays in `$settings`. See [configuration.md](configuration.md)
for the cron settings.
