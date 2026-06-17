# Migrating from Phoenix 3.x to 4.0

Phoenix 4.0 is a ground-up refactor of the 3.x codebase. The tracker protocol
behaviour is unchanged — announce, scrape, and stats respond exactly as before
— but **where files live on disk has moved**, so every operator upgrading from
3.x has three things to re-point: the **web server document root**, the
**configuration file**, and the **cron jobs**.

This guide covers a 3.x → 4.0 upgrade only. For the per-release detail, see
[CHANGELOG.md](CHANGELOG.md).

## At a glance

| What | 3.x | 4.0 |
| --- | --- | --- |
| Web root | repo root (`announce.php`, `scrape.php`, … at top level) | `public/` only |
| Bootstrap | `_phoenix.php` | `src/phoenix.php` |
| Default config | `_settings/phoenix.default.php` | `config/phoenix.default.php` |
| Custom config | `_settings/phoenix.custom.php` | `config/phoenix.custom.php` |
| Backup cron | `_cron/hourly/backup-database.php` | `bin/backup-database.php` |
| Cleanup cron | `_cron/hourly/clean-and-optimize.php` | `bin/clean-and-optimize.php` |
| Backups dir | `_backups` | `backups` |
| Minimum PHP | 7.1 | 8.2 |

## 1. Database — no migration required

**4.0 ships the same schema as 3.2.** If you are already running 3.2, there is
**nothing to change in the database** — no `ALTER TABLE`, no new columns.

The only schema change in 4.0 is organisational: the `CREATE TABLE` statements
that used to be built in PHP now live in standalone files under `sql/`
(`sql/peers.sql`, `sql/torrents.sql`, `sql/tasks.sql`). They describe the same
tables and are loaded automatically by `db_create()`; you only touch them for a
manual import (`mysql <database> < sql/peers.sql`).

> **Upgrading from 3.1 or earlier?** Apply the **3.2 SQL Migration** first — it
> adds the `size`/`listed` torrent columns and the `uploaded`/`downloaded` peer
> columns. The block is in [CHANGELOG.md](CHANGELOG.md) under **v.3.2**. Once
> that is applied you are on the 4.0 schema and need no further DB changes.

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
`clean_with_cron` (see step 5).

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

## 7. Schema upgrades (4.1 and later)

From 4.1 onwards, schema changes ship as idempotent, date-ordered SQL files in
`sql/migrations/`. Every file uses `ADD COLUMN IF NOT EXISTS` (or equivalent)
so it is safe to run more than once and causes no errors when the column is
already present.

**Via the admin panel:** navigate to `public/admin.php`, log in, and click
**Upgrade Schema**. The panel runs every migration file in filename order and
reports success or failure.

**Manually:** import each file in order against your database. If your install
uses a prefix other than the default `phoenix_`, edit the table names in the file
before importing (or let `db_migrate()` rewrite them by running via the panel).

```bash
mysql <database> < sql/migrations/2026-05-09-3.2-haggard.sql
mysql <database> < sql/migrations/2026-06-12-4.1-torrent-user-and-meta.sql
```

New installs created with 4.1 or later get the current schema directly from
`sql/*.sql` via `db_create()` and do not need to run migrations.

## Checklist

- [ ] Runtime is PHP >= 8.2 with `mysqli` and `xml`.
- [ ] (3.1 or earlier only) 3.2 SQL migration applied; otherwise no DB change.
- [ ] Document root re-pointed at `public/`; `src/`, `bin/`, `config/`, `tests/` are above the web root and not reachable over HTTP.
- [ ] Config copied to `config/phoenix.custom.php`.
- [ ] Cron jobs updated to `bin/clean-and-optimize.php` and `bin/backup-database.php`.
- [ ] `public/admin.php` moved back to `src/` after setup.
