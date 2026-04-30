# Phoenix v.3.1.5

[![Normal](https://github.com/eustasy/phoenix/actions/workflows/normal.yml/badge.svg)](https://github.com/eustasy/phoenix/actions/workflows/normal.yml)
[![PHPUnit](https://github.com/eustasy/Phoenix/actions/workflows/phpunit.yml/badge.svg?branch=main)](https://github.com/eustasy/Phoenix/actions/workflows/phpunit.yml)
[![Maintainability](https://qlty.sh/gh/eustasy/projects/Phoenix/maintainability.svg)](https://qlty.sh/gh/eustasy/projects/Phoenix)

A lightweight BitTorrent Tracker written in PHP, with an SQL backend, for people that just want to host a tracker, not the torrent listing site.

## Installation

### What Do You Need?

#### Required
* A PHP-compatible web server (Apache or Nginx).
* PHP >= 8.2 with the `mysqli` extension. The bundled `filter`, `json`, and `session` extensions are also used (these ship enabled by default).
* A MySQL or MariaDB database.

#### Recommended
* The latest version of Nginx (>= 1.18 with HTTP/2) or Apache 2.4
* [The latest supported version of PHP](https://www.php.net/supported-versions.php)
* The latest version of MariaDB

### Install Guide
1. Copy `config/phoenix.default.php` to `config/phoenix.custom.php`
2. Edit the variables in `config/phoenix.custom.php`
3. Upload Phoenix to your server.
4. Point your web server's document root at the `public/` directory. Only `public/` should be web-reachable; `src/`, `bin/`, `config/`, and `tests/` must remain outside the document root so configuration (including database credentials) is never served. See [APACHE.md](./APACHE.md) or [NGINX.md](./NGINX.md) for example configurations.
5. Load `admin.php` in your browser and run the `Setup` option.
6. After setup, move `public/admin.php` into `src/` (`mv public/admin.php src/admin.php`) so it stops being web-reachable. Move it back temporarily if you ever need to re-run setup.

## Configuration
Configuration should take place in `config/phoenix.custom.php`, NOT `config/phoenix.default.php`. Phoenix _will_ attempt to use the default configuration if yours is missing.

## Server Configuration
Phoenix ships with example web server configurations covering document root, `.php` extension stripping, and admin endpoint rate limiting:

* [APACHE.md](./APACHE.md)
* [NGINX.md](./NGINX.md)

### Cron (Automating Maintenance)
1. Edit `config/phoenix.custom.php` and set:
     - `$settings['backup_dir']` to change the backup directory. Defaults to `backups`.
     - `$settings['clean_with_cron']` to `true` to enable the script and disable occaisional cleanup on announce.
2. Edit your crontab with `crontab -e`, and add a crontab like the following. You can edit the times, and should make sure the paths are correct by running the commands after the asterisks.
```
15 * * * * php ~/phoenix/bin/clean-and-optimize.php
30 * * * * php ~/phoenix/bin/backup-database.php
```
