# Plan: API torrent management (path-per-action)

## Goal

Expose the torrent-management capabilities of
[#56](https://github.com/eustasy/Phoenix/issues/56) — list all torrents with
swarm stats, toggle `listed`, delete — through the API, as **paths rather than
POST fields to a single endpoint**. #56 itself is the *admin panel* epic item
(milestone 4.2, depends on the Foundation work); this plan builds the API
surface first and deliberately shapes the new model functions so the admin
work later reuses them unchanged.

This supersedes the `?action=` router in `public/api.php` (unreleased, so no
compatibility shim): the router file is removed and `add` moves to its own
path alongside the new actions.

## Decisions (locked)

* **Paths are files, not a router.** The PDS layout already maps URLs to
  entry-point files; a path per action means one thin `public/api/**.php`
  file per action, same as every other endpoint. No `PATH_INFO` parsing, no
  dispatch table.
* **Ownership guard on mutations.** `list`/`delist`/`delete` only operate on
  torrents whose `user` column matches the calling key's user. Rows owned by
  someone else AND rows that don't exist both report `Torrent not found.` —
  one error, so existence isn't disclosed. Announce-created rows
  (`user` NULL) are untouchable via the API (no claiming); they become the
  admin panel's job in #56.
* **`/api/torrents` returns everything.** All torrents — listed and unlisted,
  any owner — with swarm stats, matching the admin-list semantics in #56.
  API keys are operator-issued, so holders are trusted with the full list.
  (Alternative considered and deferred: scope to own + listed torrents.)
* **Response formats unchanged.** JSON by default, XML with `?xml`; the
  JSON-flag preamble is duplicated into each entry point before bootstrap,
  the same accepted duplication as the CORS comment on the read endpoints.
* **Parameters from POST or GET** on every action, consistent with `add`.
  Docs recommend POST so keys stay out of access logs.

## URL and file map

| URL | File | Does |
| --- | --- | --- |
| `/api/torrents` | `public/api/torrents.php` | Every torrent + swarm stats, incl. `user`/`listed` |
| `/api/torrent/add` | `public/api/torrent/add.php` | Add a torrent (moved from `/api.php?action=add`) |
| `/api/torrent/list` | `public/api/torrent/list.php` | Set `listed=1` on an owned torrent |
| `/api/torrent/delist` | `public/api/torrent/delist.php` | Set `listed=0` on an owned torrent |
| `/api/torrent/delete` | `public/api/torrent/delete.php` | Delete an owned torrent + its peer rows |

`torrents.php` (file) and `torrent/` (directory) don't collide. `/api` itself
maps to no action; an optional `public/api/index.php` could return a small
JSON usage object, otherwise it 404s.

## Shared plumbing

* `src/functions/api.authenticate.request.php` → `api_authenticate_request()`
  — hoists the auth block currently inlined in `api_torrent_add_controller`:
  refuse when `api_keys` is empty (`API is not enabled.`), read `key` from
  POST/GET, `api_authenticate_key()`, `tracker_error('API key is invalid.')`
  on failure. Returns the user string. Every API controller calls it first,
  so auth stays in unit-testable controller space.
* `api_torrent_add_controller` shrinks to use it; its invalid-key /
  API-disabled subprocess tests migrate to the new function's test class.

## New models (designed to be shared with #56)

* `src/model/torrents.select.all.php` → `torrents_select_all()` — per #56:
  copy of `torrents_select_listed()` without the `WHERE t.listed = 1` clause,
  adding `listed` and `user` to the SELECT/return shape.
* `src/model/torrent.select.one.php` → `torrent_select_one()` — fetch one row
  by `info_hash` (also wanted by the meta-index plan for a detail response).
* `src/model/torrent.set.listed.php` → `torrent_set_listed(mysqli, settings,
  string $info_hash, int $listed, ?string $user = null)` — `UPDATE … SET
  listed=? WHERE info_hash=?`, plus `AND user=?` when `$user` is non-null.
  The API always passes the user; #56's admin actions pass null.
* `src/model/torrent.delete.php` → `torrent_delete()` — same optional-user
  guard; `DELETE … WHERE info_hash=? [AND user=?]`.
* `src/model/peers.delete.by.torrent.php` → `peers_delete_by_torrent()` — per
  #56, pattern mirrors `peers_clean()`. Run after a successful torrent
  delete so the swarm disappears immediately rather than expiring.

## Controllers

* `src/controller/api.torrents.php` → `api_torrents_controller()` — auth,
  `torrents_select_all()`, render collection view.
* `src/controller/api.torrent.set.listed.php` →
  `api_torrent_set_listed_controller(…, int $listed)` — one controller shared
  by the `list` and `delist` entry points, which pass `1` or `0`.
* `src/controller/api.torrent.delete.php` → `api_torrent_delete_controller()`.

Mutation flow (list/delist/delete): `api_authenticate_request()` →
`maybe_binary_to_hex()` the `info_hash` (40-hex or `Info Hash is invalid.`) →
`torrent_select_one()`; missing row or `user` mismatch →
`tracker_error('Torrent not found.')` → mutate → render the torrent.
Idempotency: re-listing an already-listed torrent succeeds. The select→mutate
pair isn't atomic, but the worst race outcome is an idempotent re-set or a
delete of an already-deleted row — both harmless.

## Views

* Rename `json.torrent.add.php` / `xml.torrent.add.php` → `json.torrent.php`
  / `xml.torrent.php` (`view_torrent_json()` / `view_torrent_xml()`): the
  same single-torrent shape now answers add, list, delist, and delete
  (delete renders the row as it was removed).
* New `json.torrents.php` / `xml.torrents.php` for the collection:
  `{"torrents": […]}` / `<torrents><torrent>…</torrent></torrents>`, rows in
  the `torrents_select_all()` shape. Follow `view_index_xml()`'s
  `xml_escape()` usage for `name` and `user`.

## Server config and docs

* **APACHE.md — required fix:** the documented rewrite
  `RewriteRule ^([^/.]+)$ $1.php [L]` excludes slashes, so `/api/torrent/add`
  would never reach `add.php`. Change to `RewriteRule ^([^.]+)$ $1.php [L]`
  (still guarded by the `!-f` / `!-d` conditions) and note the API paths.
* **NGINX.md:** `try_files $uri.php` already covers subdirectories; just add
  the `/api/...` paths to the stripping note.
* README project-structure tree: replace the `api.php` line with the `api/`
  subtree. CHANGELOG Unreleased bullet rewritten for path-per-action.

## Security notes

* Key comparison stays timing-safe (`hash_equals`); the SQL `user` guard
  rides the column's case-insensitive latin1 collation, so operators should
  issue distinct, lowercase user names (note this beside `api_keys` in
  `phoenix.default.php`).
* `/api/torrents` reveals unlisted torrents to any valid key — fine for
  operator-issued keys; per-key scopes are a deferred follow-up.
* On an open tracker a deleted torrent reappears on its next announce;
  `delete` is decisive only on closed trackers (where removal also revokes
  the allowed-list entry). Document this on the endpoint.

## Tests

* Unit: `ApiAuthenticateRequestTest` (subprocess for the error exits),
  `TorrentsSelectAllTest`, `TorrentSelectOneTest`, `TorrentSetListedTest`,
  `TorrentDeleteTest`, `PeersDeleteByTorrentTest` (seed `__TEST_%` rows,
  clean in `tearDown()`), controller tests for torrents/set-listed/delete
  (ownership refusals via subprocess), renamed view tests + collection view
  tests.
* Smoke: extend the API smoke test to walk the lifecycle over real HTTP —
  add → `/api/torrents` carries it → delist → `index.php` drops it → list →
  index carries it again → other user's key gets `Torrent not found.` →
  delete → gone, peers rows removed.

## Out of scope / follow-ups

* The actual #56 deliverable (admin panel UI, CSRF forms, layout/router
  foundation) — it consumes the models built here.
* Torrent lifecycle hooks (`torrent.add` / `torrent.delete`).
* Per-key permission scopes; pagination on `/api/torrents`.

## Touched files (summary)

* `public/api.php` (removed) → `public/api/torrents.php`,
  `public/api/torrent/{add,list,delist,delete}.php` (new)
* `src/functions/api.authenticate.request.php` (new)
* `src/model/torrents.select.all.php`, `torrent.select.one.php`,
  `torrent.set.listed.php`, `torrent.delete.php`,
  `peers.delete.by.torrent.php` (new)
* `src/controller/api.torrents.php`, `api.torrent.set.listed.php`,
  `api.torrent.delete.php` (new), `api.torrent.add.php` (slimmed)
* `src/views/json.torrent.php`, `xml.torrent.php` (renamed),
  `json.torrents.php`, `xml.torrents.php` (new)
* `APACHE.md` (rewrite fix), `NGINX.md`, `README.md`, `CHANGELOG.md`,
  `config/phoenix.default.php` (user-name note)
* `tests/phoenix/*`, `tests/smoke/EndpointSmokeTest.php`
