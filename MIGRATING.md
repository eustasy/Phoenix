# Migrating from Phoenix 3.x to 4.3

Phoenix 4.0 was a ground-up refactor of the 3.x codebase, and 4.1–4.3 built on
it. The tracker protocol behaviour is unchanged — announce, scrape, and stats
respond as before — but two things every operator upgrading from 3.x must handle
did change: **where files live on disk** (moved in 4.0), so you re-point the
**web server document root**, the **configuration file**, and the **cron jobs**;
and the **database schema** (4.1 and 4.3 added tables and columns), so you apply
a few idempotent migrations.

This guide covers a 3.x → 4.3 upgrade. For per-release detail, see
[CHANGELOG.md](CHANGELOG.md).

## At a glance

| What | 3.x | 4.3 |
| --- | --- | --- |
| Web root | repo root (`announce.php`, `scrape.php`, … at top level) | `public/` only |
| Bootstrap | `_phoenix.php` | `src/phoenix.php` |
| Default config | `_settings/phoenix.default.php` | `config/phoenix.default.php` |
| Custom config | `_settings/phoenix.custom.php` | `config/phoenix.custom.php` |
| Backup cron | `_cron/hourly/backup-database.php` | `bin/backup-database.php` |
| Cleanup cron | `_cron/hourly/clean-and-optimize.php` | `bin/clean-and-optimize.php` |
| Backups dir | `_backups` | `backups` |
| Minimum PHP | 7.1 | 8.2 |

## 1. Database — apply the migrations

**4.0 itself shipped the same schema as 3.2** — its only schema change was
organisational: the `CREATE TABLE` statements that used to be built in PHP now
live in standalone files under `sql/` (`sql/peers.sql`, `sql/torrents.sql`,
`sql/tasks.sql`, plus the newer `sql/events.sql` and `sql/task.runs.sql`),
loaded automatically by `db_create()`.

But **4.1 and 4.3 added to the schema**, so a 3.x → 4.3 upgrade does need DB
changes:

- **4.1** — a new `events` stat-tracking table, and `user` / `filename` /
  `files` / `trackers` / `webseeds` columns on `torrents`.
- **4.3** — a `source` column on `tasks` plus a new `task_runs` history table,
  and an index on `torrents.listed`.

Apply them with the admin panel's **Upgrade Schema** action, or import a handful
of files by hand — see step 7. Every migration is idempotent, so re-running is
safe.

> **Upgrading from 3.1 or earlier?** The **3.2 migration** comes first — it adds
> the `size`/`listed` torrent columns and the `uploaded`/`downloaded` peer
> columns. It is the first file listed in step 7; the 4.1 and 4.3 changes follow.

## 2. Minimum PHP is now 8.2

3.x ran on PHP 7.1+. 4.0 requires **PHP >= 8.2** with the `mysqli` and `xml`
extensions (the bundled `date`, `filter`, `json`, `pcre`, and `session`
extensions are also used). Confirm your runtime before deploying:

```bash
php -v
php -m | grep -E 'mysqli|xml'
```

## 3. Move the web server document root to `public/`

This is the most important change. In 3.x the repository root was the web root,
so `announce.php`, `scrape.php`, `admin.php`, `index.php`, and `magnet.php` were
served directly. In 4.0 **only `public/` is meant to be web-served** — `src/`,
`bin/`, `config/`, and `tests/` sit one level above the document root so that
configuration (including your database credentials) can never be requested over
HTTP.

Re-point your server's document root at the `public/` directory:

- Apache: see [APACHE.md](APACHE.md)
- Nginx: see [NGINX.md](NGINX.md)

Both files also cover stripping the `.php` extension from URLs and rate-limiting
the admin endpoint.

If you ran 3.x with the endpoints at the root of a vhost, the public URLs your
clients announce/scrape against (`/announce`, `/scrape`) do **not** change —
only the filesystem path the server maps that root to does.

## 4. Move your configuration to `config/`

Your settings file moves from `_settings/phoenix.custom.php` to
`config/phoenix.custom.php`. The template is now `config/phoenix.default.php`
(do not edit the template — your overrides go in `phoenix.custom.php`).

Copy your existing `$settings[...] = ...;` overrides across. They are
forward-compatible: code reads `$settings['key']` directly with no fallback
layer, and every key still exists in the new default file. While you are there,
review the new tunables in `config/phoenix.default.php` — notably
`reject_private_ips` (rejects RFC1918/loopback source addresses by default) and
`clean_with_cron` (see step 5). Newer 4.x additions worth a look:
`stats_enabled` (opt-in event/Geography stat-tracking), `announce_external_ip`
(return the client's own IP per BEP 24), and `task_retention` (how long to keep
maintenance-task run history; `0` = forever).

## 5. Update your cron jobs

The maintenance scripts moved out of `_cron/hourly/` and into `bin/`. Update
your crontab to the new paths (adjust the leading path to wherever you deployed
Phoenix, and confirm each command runs by hand first):

```cron
15 * * * * php ~/phoenix/bin/clean-and-optimize.php
30 * * * * php ~/phoenix/bin/backup-database.php
```

- `bin/clean-and-optimize.php` replaces `_cron/hourly/clean-and-optimize.php`.
  Set `$settings['clean_with_cron'] = true;` in your config to run cleanup from
  cron and disable the occasional cleanup-on-announce.
- `bin/backup-database.php` replaces `_cron/hourly/backup-database.php`. The
  default backup directory is `backups` (was `_backups`); override it with
  `$settings['backup_dir']`.

## 6. After setup

If you re-run the installer, `public/admin.php` is web-reachable while you do.
Once setup is complete, move it back out of the document root so it stops being
served:

```bash
mv public/admin.php src/admin.php
```

Move it back into `public/` temporarily if you ever need to re-run setup.

## 7. Schema upgrades (4.1 through 4.3)

Schema changes ship two ways: **new tables** as standalone files under `sql/`
(created by `db_create()`), and **changes to existing tables** as idempotent,
date-ordered files under `sql/migrations/` (each uses `ADD COLUMN IF NOT EXISTS`
or `CREATE TABLE IF NOT EXISTS`, so re-running is safe).

**Via the admin panel (recommended):** navigate to `public/admin.php`, log in,
and click **Upgrade Schema**. It creates any new tables and runs every migration
in filename order — covering all of 4.1 and 4.3 in one click — and reports
success or failure.

**Manually:** import the new-table file and then the migrations in order. If your
install uses a prefix other than the default `phoenix_`, edit the table names in
each file before importing (or just use the panel, which rewrites the prefix for
you):

```bash
# 4.1's events table has no migration — create it from its schema file.
mysql <database> < sql/events.sql

# Migrations, in order. These add the torrent meta columns (4.1) and the task
# `source` column + `task_runs` history table (4.3).
mysql <database> < sql/migrations/2026-05-09_3.2-haggard.sql
mysql <database> < sql/migrations/2026-06-12_4.0-torrent-meta.sql
mysql <database> < sql/migrations/2026-06-18_4.3-task-history.sql
```

The 4.3 **`torrents.listed` index** is a performance-only addition: it ships in
the base schema (so new installs get it) but has no migration. On an existing
install you can add it once — optional, but it speeds the public index:

```sql
ALTER TABLE `phoenix_torrents` ADD INDEX `listed` (`listed`);
```

New installs created with 4.3 get the complete schema directly from `sql/*.sql`
via `db_create()` and need no migrations.

## Checklist

- [ ] Runtime is PHP >= 8.2 with `mysqli` and `xml`.
- [ ] Database upgraded — **Upgrade Schema** in the panel, or `sql/events.sql` plus the `sql/migrations/*.sql` files imported in order (3.1 or earlier: the 3.2 migration is the first of those).
- [ ] (Optional) `torrents.listed` index added on existing installs for faster public-index reads.
- [ ] Document root re-pointed at `public/`; `src/`, `bin/`, `config/`, `tests/` are above the web root and not reachable over HTTP.
- [ ] Config copied to `config/phoenix.custom.php`.
- [ ] Cron jobs updated to `bin/clean-and-optimize.php` and `bin/backup-database.php`.
- [ ] `public/admin.php` moved back to `src/` after setup.
