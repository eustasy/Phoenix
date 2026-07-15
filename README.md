# Phoenix v3.2.1

> **New installs should use Phoenix 4.0, on the [`main`](https://github.com/eustasy/Phoenix/tree/main) branch.** It raises the minimum to PHP >= 8.2 and reorganises the on-disk layout — the web document root, configuration, and cron paths have all moved — so upgrading is not a drop-in replacement. The database schema is unchanged from 3.2, so no DB migration is required. This `3.x` branch is maintenance-only, kept for existing installs; to upgrade, follow the [3.x → 4.0 Migration Guide](https://github.com/eustasy/Phoenix/blob/main/MIGRATING.md).

A lightweight BitTorrent Tracker written in PHP, with an SQL backend, for people that just want to host a tracker, not the torrent listing site.

## Installation

### What Do You Need?

#### Required
* A PHP compatible web-server.
* PHP >= 7.1.0 with Core, SimpleXML, date, filter, json, mysqli, pcre, & standard extensions. (Generated using [PHP CompatInfo](http://php5.laurent-laville.org/compatinfo/)
* A MySQLI supported database, such as MySQL >= 4.1

#### Recommended
* The latest version of Nginx  ( >= 1.10.0 with HTTP/2 )
* [The latest version of PHP](http://php.net/supported-versions.php)
* The latest version of MariaDB ( >= 10 )

### Install Guide
1. Upload all the `.php` files to your server, and make sure the `_settings/` directory is writable by PHP.
2. Load `admin.php` in your browser and complete the setup form. The installer tests your database credentials, creates the tables, and writes `_settings/phoenix.custom.php` for you. Set an admin password here unless you deliberately want `admin.php` left unauthenticated.
3. Configure your web server to deny `.` and `_` prefixed paths — see [Securing the Web Root](#securing-the-web-root).

#### Writing the configuration by hand
If you would rather not make `_settings/` writable, copy `_settings/phoenix.default.php` to `_settings/phoenix.custom.php`, edit it, and then load `admin.php` and run the `Setup` option to create the tables. If you do this, you **must replace every `%placeholder%` value you keep**: an unreplaced placeholder is a non-empty string, which PHP treats as `true`. Leaving `%open_tracker%` in place silently runs a closed tracker as an open one, `%db_persist%` switches persistent connections on, and `%db_prefix%` becomes a literal table-name prefix. Also set `$settings['admin_password']` — its default is empty, which disables admin authentication entirely (see [Admin Authentication](#admin-authentication)). The installer writes all of these explicitly, which is why it is the recommended path.

### Securing the Web Root

Everything Phoenix needs to serve publicly sits in the project root: `announce.php`, `scrape.php`, `index.php`, `admin.php`, and `magnet.php`. All of its internals live in `.` or `_` prefixed paths — `_settings/` (your database credentials), `_backups/` (database dumps), `_tests/`, `_onces/`, `_functions/`, `_hooks/`, `_cron/`, `_phoenix.php`, and repository metadata such as `.git/` if you deploy with git. Deny those paths in your web server so they can never be fetched or invoked over HTTP.

#### Nginx
Add this inside your `server` block, **before** the PHP `location` block — Nginx uses the first regex location that matches:
```nginx
location ~ /[._] {
	return 404;
}
```

#### Apache
Add this to your VirtualHost, or to a `.htaccess` file in the project root if `AllowOverride` permits:
```apache
RedirectMatch 404 "/[._]"
```

Afterwards, confirm the rules work — these should all return 404, while the tracker itself keeps responding:
```
curl -i https://your-tracker/_settings/phoenix.custom.php
curl -i https://your-tracker/_tests/phoenix.php
curl -i https://your-tracker/.gitignore
```

## Configuration
Configuration should take place in `_settings/phoenix.custom.php`, NOT `_settings/phoenix.default.php`. Phoenix _will_ attempt to use the default configuration if yours is missing. If you later copy extra option lines from the default file into your custom file, replace any `%placeholder%` values (see [Writing the configuration by hand](#writing-the-configuration-by-hand)).

### Admin Authentication
`$settings['admin_password']` stores a **bcrypt hash** of the admin password, never the password itself. The installer fills it in when you set a password on the setup form.

* **Empty value = no authentication.** With an empty `admin_password`, anyone who can reach `admin.php` can run its maintenance actions — and reset the tracker tables if `db_reset` is enabled. Only leave it empty if `admin.php` is not reachable from the outside.
* **Changing or resetting the password:** there is no reset flow in the admin panel. Generate a new hash and paste it into `_settings/phoenix.custom.php`:
```
php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT), PHP_EOL;"
```
* **Lost the config too?** Delete (or rename) `_settings/phoenix.custom.php` and reload `admin.php`: the installer re-runs and rewrites the whole configuration, including a fresh admin password. Your tables and their data are kept. Be aware that while the config file is missing, the installer is open to anyone who can reach `admin.php`, so do this in one sitting.
* Use the **Log out** link (`admin.php?logout=1`) to end an admin session.

### Cron (Automating Maintenance)
1. The `_cron/hourly/backup-database.php` script reads its database credentials from `_settings/phoenix.custom.php`, so it needs no editing — it only requires the `mysqldump` binary to be available on the server. Optionally set `$settings['backup_dir']` to an absolute path **outside the web root** (the default, `_backups/` in the project root, is only safe once you deny `_` prefixed paths — see [Securing the Web Root](#securing-the-web-root)) and `$settings['backup_rotate']` (days of backups to keep; default 30). The backup directory is created automatically on the first run, with `0700` permissions so only the user running the cron job can read the dumps. If the directory cannot be created or written to, or `mysqldump` fails, the script prints the reason and exits non-zero, so cron's mail or logging captures it.
2. Edit `_settings/phoenix.custom.php` and set `$settings['clean_with_cron']` to `true` instead of `false`. You can also set `$settings['clean_with_requests']` to `0` to save processing time.
3. Edit your crontab with `crontab -e`, and add a crontab like the following. You can edit the times, and should make sure the paths are correct by running the commands after the asterisks.
```
15 * * * * php ~/phoenix/_cron/hourly/clean-and-optimize.php
30 * * * * php ~/phoenix/_cron/hourly/backup-database.php
```
