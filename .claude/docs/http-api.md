# HTTP surfaces & the REST API

Every entry point lives in `public/` and is thin (see
[architecture.md](architecture.md)). This doc covers the admin panel router and
the management API in detail; announce/scrape/index are summarized in
architecture.

## Admin panel (`public/admin.php`)

Two modes, chosen by whether `config/phoenix.custom.php` exists:

- **Installer mode** (no config) → `admin_install_controller()`. First-run
  setup: collects DB credentials, tracker options, admin password, optional 2FA
  enrolment; writes `config/phoenix.custom.php` and creates the tables.
- **Normal mode** → full bootstrap → `admin_login_controller()` (returns `null`
  to fall through when authenticated/no-password, else returns the login page)
  → `admin_panel_controller()`.

`admin_panel_controller()` is a light page router on `$_GET['page']` (unknown →
dashboard). Pages: `dashboard`, `torrents`, `peers` (with `info_hash` drills
into one swarm; without, the swarm-wide listing), `geography`, `edit`, `add`,
`upload`, `support`, `utilities`, `backups`, `settings`. Each `require_once`s
and calls its `admin_*` controller, returning HTML. Nav counts are computed once
here and passed to the shared layout via `$settings` (gated on installed tables —
`COUNT` against a missing table throws under mysqli's report mode).

### Admin auth

- Auth applies **only when `admin_password` is set** (bcrypt hash). Empty
  password → auth skipped entirely (intended for first setup; secure or remove
  `admin.php` afterward).
- Failed logins are throttled (`admin_login_delay`, escalating, capped by
  `admin_login_delay_max`).
- Optional TOTP second factor when `admin_totp_secret` is set (needs
  `eustasy/authenticatron`; QR needs ext-gd). Lost device → remove the secret
  line to revert to password-only.
- Sessions are hardened; all mutating admin forms carry a CSRF token
  (`auth.csrf.token.php` / `auth.csrf.verify.php`).
- `admin.php` sends `Content-Type: text/html; charset=UTF-8` before any output,
  overriding the bootstrap's `iso-8859-1` (set for the binary tracker protocol)
  so UTF-8 torrent names render correctly.

Relevant functions: `src/functions/auth.*.php` (csrf, login throttle, session,
verify). Controllers: `src/controller/admin.*.php`.

## Management REST API (`public/api/`)

An authenticated API for managing torrents. **No central router** — each
endpoint is its own entry point that pre-sets `$_GET['json']` (so even bootstrap
errors serialise as JSON, not bencode), bootstraps, and delegates to an
`api_*_controller`. Responses are JSON by default, XML with `?xml`.

| Endpoint | Method | Controller | Notes |
| --- | --- | --- | --- |
| `/api` | GET | `api_index_controller` | Version probe. **Unauthenticated**, no torrent data. |
| `/api/torrents` | GET | `api_torrents_controller` | Caller's torrents + swarm stats (admin sees all). |
| `/api/torrent/add` | POST | `api_torrent_add_controller` | Add a torrent (fields or uploaded `.torrent`). |
| `/api/torrent/update` | POST | `api_torrent_update_controller` | Edit a torrent's fields. |
| `/api/torrent/list` | POST | `api_torrent_set_listed_controller` | Show on the public index. |
| `/api/torrent/delist` | POST | `api_torrent_set_listed_controller` | Hide from the public index. |
| `/api/torrent/delete` | POST | `api_torrent_delete_controller` | Delete torrent + its peers (gated by `api_allow_delete`; `*` admin always allowed). |

### API auth model

Two helpers, both returning the **user to act as** or exiting via
`tracker_error()`:

- **`api_authenticate_request()`** — read path (`/api/torrents`).
- **`api_authenticate_mutation()`** — write path (`/api/torrent/*`).

Each accepts two credential types:

1. **`Authorization: Bearer <key>`** — validated against `$settings['api_keys']`
   (`'user' => sha256-hash` pairs; `api_authenticate_key()` hashes the presented
   key and compares). Keys are created on the admin **API Keys** page, which shows
   each key once and stores only its hash. **No CSRF** on either path: a bearer
   key isn't an ambient browser credential, so it can't be forged cross-site. The
   user may be a normal owner (scoped to its own torrents) or the reserved `'*'`
   admin (acts on any torrent, sees the full list).
2. **A logged-in `admin.php` session** → resolves to the `'*'` admin. The read
   path needs **no CSRF** (the response can't be read cross-origin, so a forged
   request leaks nothing); the **write path requires a valid CSRF token** (the
   session cookie is sent automatically, so a state change must prove intent).

Ownership: the user a key belongs to is recorded on each torrent it adds and
scopes list/delist/delete to that user's own rows. `'*'` is the admin and can
act on anything, including announce-created rows with no owner. Empty `api_keys`
disables key auth (`'API is not enabled.'`). User names are lowercase
(case-insensitive collation). See [configuration.md](configuration.md).

Auth/key functions: `src/functions/api.*.php`. CORS: the API sends **no**
`Access-Control-Allow-Origin` (unlike the public read endpoints
announce/scrape/index, which send `*`).

## Torrent parsing & upload

`add`/`upload` can parse an uploaded `.torrent` server-side: `torrent_parse()`
(`src/functions/torrent.parse.*.php`) bencode-decodes and extracts
name/size/files/trackers/webseeds; `torrent.normalize.meta.php` and the
`sanitize.torrent.meta.*.php` helpers normalize and sanitize the stored meta.
Max upload size is `torrent_upload_max`.
