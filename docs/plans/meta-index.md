# Plan: Meta-Index (trackers, webseeds, filenames)

## Goal

Extend the torrent index so each torrent can carry the presentation/metadata that
**bits** stored but the v4 rewrite dropped: extra announce URLs (`trackers`), web
seeds (`webseeds`), and file information (`filename` + full `files` list). This
lets the public index and API serve enough data to rebuild a complete magnet link
or render a file tree — instead of the bare `name`/`size`/`downloads` v4 exposes
today.

This is **additive only**. Default behaviour is unchanged: columns are nullable,
empty by default, and the public index keeps its current output unless an operator
opts into showing meta.

## Decisions (locked)

* **Population:** two paths — (1) explicit fields on the existing add flow (API +
  admin panel), and (2) server-side `.torrent` parsing that auto-fills every meta
  field from an uploaded file (mirrors the client-side parser already in
  `public/magnet.php`).
* **Filenames:** store **both** a single `filename` (primary display name) **and**
  a structured `files` list (JSON: path + length per file).

## Schema

Extend `sql/torrents.sql` with four nullable columns (current PK / engine unchanged):

```sql
`filename`  varchar(255) NULL,            -- primary/top-level display filename
`files`     longtext     NULL,            -- JSON: [{"path":"a/b.mkv","length":123}, ...]
`trackers`  longtext     NULL,            -- newline-delimited announce URLs (extra tiers)
`webseeds`  longtext     NULL,            -- newline-delimited url-list (BEP 19) entries
```

Format rationale:

* `trackers` / `webseeds` are newline-delimited plain text to round-trip directly
  with `magnet.php`'s textarea UI and to match bits' `longtext` storage.
* `files` is JSON because it is structured (path + length); decode with
  `json_decode(..., true)`, cast `length` to `int` on read.
* Keep nullable (not `NOT NULL DEFAULT ''`) so "no meta" is distinguishable from
  "empty meta", and so the migration is a pure `ADD COLUMN`.

`db_create()` needs no change — it reads `sql/torrents.sql` verbatim. **But
`CREATE TABLE IF NOT EXISTS` will not alter existing installs**, so a migration is
required (see below).

## Migration (existing installs)

v4 has no migration mechanism yet; this plan introduces a minimal one.

* Add `sql/migrations/` with dated, idempotent files. First file adds the four
  columns using MariaDB's `ADD COLUMN IF NOT EXISTS`:

  ```sql
  ALTER TABLE `phoenix_torrents`
    ADD COLUMN IF NOT EXISTS `filename` varchar(255) NULL,
    ADD COLUMN IF NOT EXISTS `files`    longtext     NULL,
    ADD COLUMN IF NOT EXISTS `trackers` longtext     NULL,
    ADD COLUMN IF NOT EXISTS `webseeds` longtext     NULL;
  ```

* Prefix rewriting reuses `db_create()`'s `str_replace('phoenix_', $prefix)` logic;
  extract that into a small helper (`src/functions/db.apply.prefix.php`) shared by
  create + migrate so the rule lives in one place.
* Expose it as an admin-panel **"Upgrade schema"** action (new
  `admin_migrate_controller`, gated like the other admin actions) that runs any
  unapplied migration files, and document the manual `mysql < sql/migrations/*.sql`
  path in the install docs. New installs get the columns straight from the updated
  `sql/torrents.sql`, so they skip migrations entirely.

## Server-side `.torrent` parsing

New, self-contained, unit-testable pieces:

* `src/functions/bencode.decode.php` → `bencode_decode(string $data): array`
  The inverse of the existing `bencode_encode()`. Must additionally record the
  **raw byte range of the `info` dict** (as `magnet.php` does in JS) so the
  info-hash can be computed. Suggested return shape:
  `['value' => mixed, 'info_raw' => string|null]`.
* `src/functions/torrent.parse.php` → `torrent_parse(string $raw): array|false`
  Uses `bencode_decode`, then derives:
  * `info_hash` = `sha1($info_raw)` (already 40-char hex — no `maybe_binary_to_hex`
    needed),
  * `name` / `filename` from `info.name` (prefer `name.utf-8`),
  * `size` from `info.length` or the sum of `info.files[*].length`,
  * `files` list from `info.files` (path join + length); single-file torrents get
    one entry,
  * `trackers` from `announce` + `announce-list`,
  * `webseeds` from `url-list`.
  Returns a normalized array ready for `torrent_add`, or `false` on malformed input.

Parsing is reused by both the API (multipart `.torrent` upload) and the admin form.
Keep a size cap on accepted uploads (new setting, below) — bencode decoding is
recursive, so bound input length and nesting depth defensively.

## Population paths

### API (`src/controller/api.torrent.add.php`)

Add optional params, all sanitized before insert:

* `filename` (string, trim to 255),
* `files` (JSON string; validate it decodes to a list of `{path,length}`),
* `trackers`, `webseeds` (newline-delimited; trim, drop blanks, validate as URLs),
* `torrent` (multipart file upload): when present, run `torrent_parse()` and use its
  output as the base, with any explicit params overriding parsed values.

`torrent_add()` (`src/model/torrent.add.php`) extends its INSERT column list and the
`$torrent` array shape (update the docblock `@param`). Still add-only (1062 →
`'exists'`).

### Admin panel (`src/controller/admin.panel.php` + `src/views/html.admin.php`)

Add the same fields to the torrent-add form, plus a `.torrent` file input that posts
to the parse path. Reuse the API sanitizers so validation lives in one place.

## Read paths

* `src/model/torrents.select.listed.php`: include `filename`, `files`, `trackers`,
  `webseeds` in the selected/normalized row (decode `files` JSON; split tracker/seed
  text to arrays). Update the `@return` shape.
* New `src/model/torrent.select.one.php` (by `info_hash`) for a per-torrent
  detail/magnet response.
* Views: extend `json`/`xml` index output to carry the meta fields; optionally
  render a file list in `html.index`. Gate richer public output behind a setting so
  default behaviour is preserved.
* Optional: a server-built magnet endpoint/view that assembles the full
  `magnet:?xt=...&tr=...&ws=...` from stored meta, so `magnet.php` can offer a
  "load from tracker" path in addition to local file parsing.

## Settings (`config/phoenix.default.php`)

* `$settings['index_show_meta'] = false;` — when true, the public index includes
  trackers/webseeds/files in JSON/XML/HTML. Default off = unchanged output.
* `$settings['torrent_upload_max'] = 1048576;` — max accepted `.torrent` upload size
  (bytes) for server-side parsing.

(Per project convention, every new tunable gets a default + one-line comment here;
code reads `$settings[...]` with no fallback.)

## Tests (`tests/phoenix/`)

* `BencodeDecodeTest` — round-trip against `bencode_encode`; malformed input;
  correct `info` byte-range capture.
* `TorrentParseTest` — fixture `.torrent` files (single-file and multi-file);
  assert computed `info_hash`, summed `size`, `files`, `trackers`, `webseeds`.
* `TorrentAddTest` — extend to cover the new columns and the JSON `files` round-trip.
* `TorrentsSelectListedTest` — meta fields decoded and shaped correctly.
* Migration idempotency — running the `ADD COLUMN IF NOT EXISTS` twice is a no-op.

## Out of scope / follow-ups

* No editing/overwrite of existing torrents (add-only contract preserved).
* No re-validation that stored `info_hash` matches uploaded file content beyond the
  computed hash.
* Pagination/search on the public index (separate concern).

## Touched files (summary)

* `sql/torrents.sql` (+ new `sql/migrations/*.sql`)
* `src/functions/bencode.decode.php`, `torrent.parse.php`, `db.apply.prefix.php` (new)
* `src/model/torrent.add.php`, `torrents.select.listed.php`, `torrent.select.one.php` (new)
* `src/controller/api.torrent.add.php`, `admin.panel.php`, `admin.migrate.php` (new)
* `src/views/html.admin.php`, `json`/`xml` index views, `html.index.php`
* `config/phoenix.default.php`
* `public/admin.php` (route the migrate action), docs
* `tests/phoenix/*`
