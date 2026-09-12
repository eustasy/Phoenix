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

Backups are written by `bin/backup-database.php` (cron) and the admin **Backups**
page, both through the same `db_backup()`.

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

They land in
`$settings['backup_dir']` — `backups/` in the project root by default — named
`<db_name>.<YYYYMMDD_HHMM>.sql`, with `.gz` appended when `backup_compress` is
on, which it is by default:

```text
backups/phoenix.20260911_1559.sql.gz
```

Dumps older than `backup_retention` days (30 by default) are deleted on each
run, so check the directory holds what you expect before relying on it.

**Know what is in the dump before you import it.** Three properties decide how a
restore behaves, and none of them are the defaults `mysqldump` would give you:

- Rows are written as **`REPLACE INTO`**, not `INSERT`. Importing over live data
  overwrites rows whose primary key matches and leaves everything else in place
  — it is a merge, not a replacement. Rows created since the dump survive.
- There is **no `DROP TABLE`**, and the `CREATE TABLE` statements have no
  `IF NOT EXISTS`. Importing into a database that already has the tables fails
  on the first `CREATE`, and the client aborts there by default.
- **`peers` is structure-only.** The swarm is ephemeral, so it is dumped without
  rows and comes back empty. Clients repopulate it as they announce; nothing is
  lost that would not have expired anyway.

### Into an empty database

The clean restore — the dump recreates the tables and fills them:

```bash
# Compressed (the default)
gunzip -c backups/phoenix.20260911_1559.sql.gz | mariadb -u <user> -p <database>

# Uncompressed
mariadb -u <user> -p <database> < backups/phoenix.20260911_0330.sql
```

### Into a database that already has the tables

Rolling data back while keeping the schema in place. The `CREATE TABLE`
statements will error; `--force` skips them so the `REPLACE INTO` rows still
apply:

```bash
gunzip -c backups/phoenix.20260911_1559.sql.gz | mariadb --force -u <user> -p <database>
```

Read the errors it prints rather than ignoring them: `Table ... already exists`
is the expected one, anything else is not.

> Use the **`mariadb`** client where the server is MariaDB. Dumps from MariaDB
> 11+ open with a `/*M!999999\- enable the sandbox mode */` directive that
> Oracle's `mysql` client does not understand.

### Afterwards

Check the tracker responds and the admin dashboard's counts look right. If you
restored into a fresh database, confirm `config/phoenix.custom.php` still points
at it.

## Start over completely

Delete `config/phoenix.custom.php` and re-run **Setup**. The installer writes a
fresh setup token to `config/.phoenix-setup-token` which you must supply to
proceed — see [Installation](./README.md#installation).

If you relocated `public/admin.php` out of the web root after setup, restore it
first. This resets configuration only; it does not touch the database.
