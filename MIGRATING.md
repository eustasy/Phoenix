# Migrating from Phoenix 3.2.x to 5.0

Phoenix 5.0 is a ground-up rebuild of the 3.x codebase. The tracker protocol is
unchanged — announce and scrape answer as they did — but four things every
operator must handle did change:

1. **Where files live.** Only `public/` is web-served now.
2. **Where configuration lives**, and what some settings are called.
3. **Where the cron jobs live**, and there are three of them.
4. **The database** — new columns, new tables, and a new storage engine.

This guide is written for **3.2.x** — the last stable line — and the SQL in
step 2 assumes that schema. On 3.1 or earlier, upgrade to 3.2.2 first; its own
schema changes are not repeated here.

Budget an hour, and take a backup first. For per-release detail see
[CHANGELOG.md](CHANGELOG.md).

## At a glance

| What | 3.2.x | 5.0 |
| --- | --- | --- |
| Web root | repo root (`announce.php`, `scrape.php`, … at top level) | `public/` only |
| Bootstrap | `_phoenix.php` | `src/phoenix.php` |
| Default config | `_settings/phoenix.default.php` | `config/phoenix.default.php` |
| Custom config | `_settings/phoenix.custom.php` | `config/phoenix.custom.php` |
| Cleanup cron | `_cron/hourly/clean-and-optimize.php` | `bin/prune-database.php` |
| Rebuild cron | — | `bin/optimize-database.php` |
| Backup cron | `_cron/hourly/backup-database.php` | `bin/backup-database.php` |
| Backups dir | `_backups` | `backups` |
| Storage engine | MyISAM | InnoDB |
| Minimum PHP | 7.1 | 8.2 |

## 0. Back up first

Every step below is reversible except the database ones. Take a dump you can
return to:

```bash
mysqldump --single-transaction <database> | gzip > phoenix-pre-5.0.sql.gz
```

## 1. Check the runtime

5.0 requires **PHP >= 8.2** with the `mysqli` and `xml` extensions:

```bash
php -v
php -m | grep -E 'mysqli|xml'
```

## 2. Update the database

Everything the schema needs, in one block. It assumes the default `phoenix_`
prefix — if yours differs, change the table names before running it.

Run it once. These are plain `ALTER`/`CREATE` statements that work on both MySQL
and MariaDB, and running them a second time errors rather than doing damage —
"Duplicate column name" or "Table already exists" means that part is already
applied.

```sql
-- Torrent ownership and meta: who added a torrent via the API, and the
-- filename, file list, trackers and webseeds shown on the index and in magnets.
-- The `listed` index keeps the public index off a full table scan.
ALTER TABLE `phoenix_torrents`
  ADD COLUMN `user` varchar(255) NULL FIRST,
  ADD COLUMN `filename` varchar(255) NULL,
  ADD COLUMN `files` longtext NULL,
  ADD COLUMN `trackers` longtext NULL,
  ADD COLUMN `webseeds` longtext NULL,
  ADD INDEX `listed` (`listed`);

-- `left` becomes SIGNED, so the -1 "bytes remaining unknown" sentinel a client
-- sets by omitting `left` can be stored. Unsigned, it threw out-of-range under
-- a strict-mode database and returned a 500 on the announce.
ALTER TABLE `phoenix_peers`
  MODIFY `left` bigint(20) NOT NULL DEFAULT '0';

-- Maintenance runs record who triggered them: cron, an announce, or the panel.
ALTER TABLE `phoenix_tasks`
  ADD COLUMN `source` varchar(8) NOT NULL DEFAULT '' AFTER `value`;

-- The full history behind that, shown on the Task History page.
CREATE TABLE `phoenix_task_runs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(16) NOT NULL,
  `value` int(10) NOT NULL,
  `source` varchar(8) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- The stat-tracking ledger. Created whether or not you enable stats, so
-- `stats_enabled` is a config flip rather than a schema change later. It stores
-- a coarse client label and country code, never an address. The `geo` index
-- covers the Geography aggregations, which would otherwise scan the whole
-- ledger every time the page loads.
CREATE TABLE `phoenix_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `time` int(10) unsigned NOT NULL,
  `info_hash` varchar(40) NOT NULL,
  `event` varchar(16) NOT NULL,
  `client` varchar(64) NOT NULL DEFAULT '',
  `user` varchar(255) NOT NULL DEFAULT '',
  `country` char(2) NOT NULL DEFAULT '',
  `continent` char(2) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `info_hash` (`info_hash`),
  KEY `geo` (`event`, `country`, `info_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

## 3. Convert the tables to InnoDB

5.0 assumes InnoDB. `db_create()` only creates missing tables, so an existing
database keeps whatever engine it has — nothing in the app will convert it for
you, and a tracker left on MyISAM works but serialises its writes on a table
lock.

Each `ALTER` rebuilds its table and holds it locked for the duration, so run
them during a quiet window:

```sql
ALTER TABLE `phoenix_events`    ENGINE=InnoDB;
ALTER TABLE `phoenix_peers`     ENGINE=InnoDB;
ALTER TABLE `phoenix_tasks`     ENGINE=InnoDB;
ALTER TABLE `phoenix_task_runs` ENGINE=InnoDB;
ALTER TABLE `phoenix_torrents`  ENGINE=InnoDB;
```

Confirm afterwards — all five should read `InnoDB`:

```sql
SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'phoenix\_%';
```

[LIMITS.md](LIMITS.md) covers what InnoDB costs and what it buys.

## 4. Re-point the document root

This is the change most likely to take a site down if missed. In 3.2.x the
repository root was the web root and every endpoint sat at the top level. In
5.0 **only `public/` is meant to be served** — `src/`, `bin/`, `config/`,
`sql/` and `tests/` must all be unreachable over HTTP.

Point your vhost at `<install>/public` and restart. Example configurations,
including `.php` extension stripping and `Authorization` passthrough, are in
[APACHE.md](APACHE.md) and [NGINX.md](NGINX.md).

Your public URLs are unchanged if you were serving from a vhost root:
`/announce`, `/scrape`, `/index.php` all still resolve.

## 5. Move the configuration

Copy `_settings/phoenix.custom.php` to `config/phoenix.custom.php`. The format
is unchanged, but **several settings were renamed**. An unrecognised key is
ignored silently and the new default applies, so check these:

| 3.2.x | 5.0 |
| --- | --- |
| `external_ip` | `allow_client_ip` |
| `announce_interval` | `announce_rec_interval` |
| `min_interval` | `announce_min_interval` |
| `random_limit` | `random_peers_threshold` |
| `clean_with_requests` | `clean_request_percent` |
| `allow_any_proxy` | `trust_any_forwarded` |
| `backup_rotate` | `backup_retention` |

`config/phoenix.default.php` lists every setting with its default and a comment;
[CONFIGURATION.md](CONFIGURATION.md) explains the ones worth thinking about.

**If you sit behind a proxy or CDN, read the forwarded-header section.** 3.2.x's
`honor_xff` is gone, and the replacement trusts **nothing** by default — you
must name the header your proxy sets in `forwarded_headers` and list the
proxy's ranges in `trusted_proxies`, or every peer will appear to announce from
the proxy's address.

## 6. Update the cron jobs

Three entries now, on deliberately different schedules:

```cron
*/15 * * * * php ~/phoenix/bin/prune-database.php
15  3 * * * php ~/phoenix/bin/optimize-database.php
30  3 * * * php ~/phoenix/bin/backup-database.php
```

`prune-database.php` exits immediately unless `clean_with_cron` is `true`, so
set that too — otherwise cleanup falls back to running inline on a fraction of
announces.

## 7. Set an admin password

The panel at `public/admin.php` now requires one. On first load it presents a
one-time set-password gate; the same page can enrol a TOTP second factor.

> The gate is unauthenticated by nature — anyone who reaches it while it is
> showing can claim the panel. Complete it promptly, and ideally before the site
> is publicly reachable.

If you would rather run the panel with no password at all — because a reverse
proxy or an IP allowlist already protects it — set
`$settings['admin_auth_optional'] = true;` instead.

## 8. Check it works

- `/announce` answers a bencoded response to a well-formed announce.
- `/scrape` answers, and reports `version` and `release`.
- `/index.php` renders if `public_index` is on.
- `public/admin.php` logs in, and the Dashboard shows your torrent and peer counts.
- **Server Support** reports no faults.
- **DB Utilities → Check** reports no table errors.

## Checklist

- [ ] Pre-upgrade database dump taken.
- [ ] Runtime is PHP >= 8.2 with `mysqli` and `xml`.
- [ ] Schema updated with the SQL block in step 2.
- [ ] All five tables converted to InnoDB.
- [ ] Document root re-pointed at `public/`; `src/`, `bin/`, `config/`, `sql/`, `tests/` unreachable over HTTP.
- [ ] Config copied to `config/phoenix.custom.php`, renamed settings updated.
- [ ] `forwarded_headers` and `trusted_proxies` set, if behind a proxy.
- [ ] Cron jobs updated to the three `bin/` scripts, and `clean_with_cron` enabled.
- [ ] Admin password set, and 2FA enrolled.
- [ ] Announce, scrape, index and admin panel all verified.
