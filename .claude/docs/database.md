# Database

MySQL/MariaDB via `mysqli`. Tables are MyISAM, chosen for a write-heavy workload
with no transactions or foreign keys.

## Tables

Schema lives in `sql/<table>.sql`, one `CREATE TABLE` per file using the literal
default prefix `phoenix_`. The actual prefix is `$settings['db_prefix']`.

- **`<prefix>peers`** — active peers, ephemeral. PK `(info_hash, peer_id)`.
  Cleanup deletes rows where `updated < time - 3 * announce_rec_interval`.
- **`<prefix>torrents`** — tracked torrents. PK `info_hash`. Holds `name`,
  `size`, `listed`, `downloads`, torrent meta (filename, files,
  trackers, webseeds) and `user` (owner; the API key that created it).
- **`<prefix>tasks`** — last-run log per maintenance task (`name` PK).
- **`<prefix>task_runs`** — task run history (pruned by `task_retention`).
- **`<prefix>events`** — optional privacy-preserving stat ledger. Exists from
  install; writing is gated by `stats_enabled`. See [stats-hooks.md](stats-hooks.md).

## Creating & migrating

- `db_create()` (`src/model/db.create.php`) reads each `sql/*.sql`, rewrites the
  prefix to `$settings['db_prefix']` (via `db_apply_prefix`) if different, and
  executes against the connection's selected database. Files are also importable
  manually: `mysql <database> < sql/peers.sql`.
- `db_migrate()` (`src/model/db.migrate.php`) runs every file in
  `sql/migrations/*.sql` in filename order, each time. Migrations are written to
  be **idempotent** (`ADD COLUMN IF NOT EXISTS`, …) so there is **no
  applied-migrations bookkeeping** — every file runs on every call. It strips
  `--` line comments, splits on `;`, and executes each statement (mysqli runs
  only the first statement of a multi-statement string). Migration files use the
  literal `phoenix_` prefix, rewritten at run time.
- New migration: add `sql/migrations/<date>_<slug>.sql`, idempotent, default
  prefix.

## SQL injection defense

The `src/model/` layer is fully parameterized (`mysqli_execute_query` with bound
`?` params) — that's the actual SQL-injection defense, not string concatenation.

`info_hash` and `peer_id` are still stored as **40-char hex strings**, not raw
20-byte binary. Conversion happens at the boundary via `maybe_binary_to_hex()`
(`sanitize.maybe_binary_to_hex.php`), which normalizes and validates the value
before it's bound as a parameter. Anything that fails sanitization returns
`false` and is filtered out before reaching the SQL layer.

When adding a query, keep all SQL in `src/model/`, bind values as parameters
(never concatenate them into the query string), and ensure any hash/peer-id
input has already passed through the sanitizer.

## `db_host` `p:` gotcha

When `db_persist` is true, `db_persist_host()` prepends `p:` to
`$settings['db_host']` (mysqli's persistent-connection marker), and the
bootstrap mutates `$settings['db_host']` in place. Anywhere outside
`mysqli_connect` that reads `db_host` (e.g. `bin/backup-database.php` writing a
credentials file for `mysqldump`) must strip the `p:` prefix.

## Cast on read

mysqli returns numeric columns as strings. Cast to `int` in the model or before
encoding so views emit numbers, not strings. See [views.md](views.md).
