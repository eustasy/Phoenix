# Phoenix v.3.12

[![Codacy Badge](https://api.codacy.com/project/badge/10f5af9881b4412093e91d68086fd468)](https://www.codacy.com/app/lewisgoddard/phoenix)
[![Code Climate](https://codeclimate.com/github/eustasy/phoenix/badges/gpa.svg)](https://codeclimate.com/github/eustasy/phoenix)
[![Travis CI](https://travis-ci.org/eustasy/phoenix.svg)](https://travis-ci.org/eustasy/phoenix)

A lightweight BitTorrent Tracker written in PHP, with an SQL backend, for people that just want to host a tracker, not the torrent listing site.

## Installation

### What Do You Need?

#### Required
* PHP >= 4
* A MySQLI supported database, such as MySQL >= 4.1

#### Recommended
* Nginx >= 1.8.0, Apache, OR lighttpd.
* [A supported version of PHP](http://php.net/supported-versions.php)
* MySQL >= 5.5 OR MariaDB >= 5.5

#### _Really_ Recommended
* Nginx >= 1.10.0 with HTTP/2
* [The latest version of PHP](http://php.net/supported-versions.php)
* MariaDB >= 10

### Install Guide
1. Copy `_settings/phoenix.default.php` to `_settings/phoenix.custom.php`
2. Edit the variables in `_settings/phoenix.custom.php`
2. Upload all the `.php` and `.sh` files to your server.
4. Load `admin.php` in your browser and run the `Setup` option.

## Configuration
Configuration should take place in `_settings/phoenix.custom.php`, NOT `_settings/phoenix.default.php`. Phoenix _will_ attempt to use the default configuration if yours is missing.

### Cron (Automating Maintenance)
1. Configure `_cron/hourly/backup-database.sh` by changing the path in the second line, and the username, password, database, and file in the last three.
2. Edit `_settings/phoenix.custom.php` and set `$settings['clean_with_cron']` to `true` instead of `false`. You can also set `$settings['clean_with_requests']` to `0` to save processing time.
3. Edit your crontab with `crontab -e`, and add a crontab like the following. You can edit the times, and should make sure the paths are correct by running the commands after the asteriks.
```
15 * * * * php ~/phoenix/_cron/hourly/clean-and-optimize.php
30 * * * * ~/phoenix/_cron/hourly/backup-database.sh
```
