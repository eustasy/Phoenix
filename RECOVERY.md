# Recovery

Getting back in, and getting data back. Each section stands alone — you do not
need the ones above it.

## Table of Contents

- [Reset the admin password](#reset-the-admin-password)
- [Disable two-factor authentication](#disable-two-factor-authentication)
- [Restore the database from a backup](#restore-the-database-from-a-backup)
  - [Into an empty database](#into-an-empty-database)
  - [Into a database that already has the tables](#into-a-database-that-already-has-the-tables)
- [Start over completely](#start-over-completely)

## Reset the admin password

The password is stored as a bcrypt hash in `$settings['admin_password']` in
`config/phoenix.custom.php`, and you normally change it from the panel's
**Settings** page. Locked out, there are two routes — the first needs `config/`
writable, the second does not.

**Reset from the panel.** Remove the `$settings['admin_password']` line from
`config/phoenix.custom.php` (or set it to `''`). On the next load, `admin.php`
presents a one-time **"set admin password"** gate — the same ≥12-character
policy, with optional TOTP re-enrolment — and writes the new hash for you, so
there is no manual hashing.

> The gate is **unauthenticated**: anyone who can reach `public/admin.php` while
> it is showing can claim the panel. Only do this while the panel is not
> publicly reachable, and finish it promptly.

**Set the hash directly.** When `config/` is not writable, or you would rather
not expose that gate:

```bash
php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Set `$settings['admin_password']` to the printed value. Editing the file
directly bypasses the length policy, so pick a strong password yourself.

There is also `$settings['admin_auth_optional'] = true`, which runs the panel
with **no** password at all. That is only for an `admin.php` already protected
by other means — reverse-proxy auth, an IP allowlist — never as a way out of a
lockout.

## Disable two-factor authentication

Lost the authenticator device, or need to re-enrol on a new one. Remove the
`$settings['admin_totp_secret'] = '...';` line from `config/phoenix.custom.php`.

The panel reverts to password-only on the next load, and you can re-enrol from
**Settings** — see
[Two-Factor Authentication](./CONFIGURATION.md#two-factor-authentication-recommended).

This is independent of the password: you can drop the second factor and keep the
password you already have. If you have lost both, do this and
[reset the password](#reset-the-admin-password).

## Restore the database from a backup

Backups are written by `bin/backup-database.php` (cron) and the admin
**Backups** page, both through the same `db_backup()`.

> **If you are reading this during an incident, check you have backups at all.**
> Nothing schedules itself — a default install dumps only when someone clicks
> **Backups** in the panel. If that is the situation, take one now before you
> change anything, then set up the cron so the next incident goes better:
>
> ```cron
> 30 3 * * * php ~/phoenix/bin/backup-database.php
> ```
>
> See [Backups](./CONFIGURATION.md#backups-recommended) for the settings.

A backup is a **dated directory**, not a single file. It lands in
`$settings['backup_dir']` — `backups/` in the project root by default — and
holds the schema and one data file per table, each gzipped unless
`backup_compress` is off:

```text
backups/phoenix.20260912_033000/
  schema.sql.gz      every table's CREATE, plus routines and triggers
  events.sql.gz      data only
  tasks.sql.gz
  task_runs.sql.gz
  torrents.sql.gz
```

Splitting it that way is what makes a restore selective: you import the schema
and then only the tables you actually want back. The admin Backups page offers
each file as its own download for the same reason.

Backups older than `backup_retention` days (30 by default) are deleted on each
run, so check the directory holds what you expect before relying on it.

**Two things to know before importing.**

Rows are written as **`REPLACE INTO`**, not `INSERT`. Importing over live data
overwrites rows whose primary key matches and leaves everything else in place —
it is a merge, not a replacement, so rows created since the dump survive. To
replace a table outright rather than merge into it, empty it first.

**`peers` has no data file.** The swarm is ephemeral, so it is dumped
structure-only inside `schema.sql` and comes back empty. Clients repopulate it
as they announce; nothing is lost that would not have expired anyway.

> Use the **`mariadb`** client where the server is MariaDB. Dumps from MariaDB
> 11+ open with a `/*M!999999\- enable the sandbox mode */` directive that
> Oracle's `mysql` client does not understand.

### Into an empty database

Schema first, then whichever data files you want:

```bash
cd backups/phoenix.20260912_033000

gunzip -c schema.sql.gz   | mariadb -u <user> -p <database>
gunzip -c torrents.sql.gz | mariadb -u <user> -p <database>
gunzip -c events.sql.gz   | mariadb -u <user> -p <database>
```

With `backup_compress` off the files are plain `.sql` and import directly:

```bash
mariadb -u <user> -p <database> < schema.sql
```

Order barely matters beyond schema-before-data: Phoenix has no foreign keys, so
the data files are independent of each other and can go in any order.

### Into a database that already has the tables

Skip `schema.sql` and import only the data files you want. Nothing collides,
because the `CREATE TABLE` statements live in a file you are not importing:

```bash
# Roll back just the torrents table, leaving events and tasks alone.
gunzip -c backups/phoenix.20260912_033000/torrents.sql.gz \
  | mariadb -u <user> -p <database>
```

This is the common case — recovering one table after a bad edit, without
touching the rest of the tracker.

### What you probably do not want back

`task_runs` is the maintenance history behind the
[Task History](./CONFIGURATION.md#cron-automating-maintenance) page. Restoring
it brings back a log of when cleanups ran, which is rarely what you are after
mid-incident. Skip it unless you want the history.

### Afterwards

Check the tracker responds and the admin dashboard's counts look right. If you
restored into a fresh database, confirm `config/phoenix.custom.php` still points
at it. If you imported `torrents.sql` without `events.sql`, the dashboard's
download counters and the Bandwidth page will disagree until new events
accumulate — `torrents.downloads` is a running total, the ledger is the history
behind it.

## Start over completely

Delete `config/phoenix.custom.php` and re-run **Setup**. The installer writes a
fresh setup token to `config/.phoenix-setup-token` which you must supply to
proceed — see [Installation](./README.md#installation).

If you relocated `public/admin.php` out of the web root after setup, restore it
first. This resets configuration only; it does not touch the database.
